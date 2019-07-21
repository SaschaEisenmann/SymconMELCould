<?php
class MELCloudControl extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString('Email', '');
        $this->RegisterPropertyString('Password', '');

        $this->RegisterPropertyString('Token', '');
        $this->RegisterPropertyString('TokenExpiry', '');
        $this->RegisterPropertyInteger('Category', 0);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
    }

    public function Sync()
    {
        if (!$this->HasValidToken()) {
            $this->CreateToken();
        }

        $devices = $this->ListDevices();
        if (isset($devices)) {
            foreach ($devices as $device) {
                $this->CreateOrUpdateDevice($device);
            }
        }

    }

    private function ListDevices() {
        $token = $this->ReadPropertyString('Token');

        $url = "https://app.melcloud.com/Mitsubishi.Wifi.Client/User/ListDevices/";

        $headers = array();
        $headers[] = "Accept: application/json";
        $headers[] = "X-MitsContextKey: $token";

        $locations = $this->Request($url, 'GET', array(), $headers);

        $devices = array();
        foreach ($locations as $location) {
            foreach ($location["Structure"]["Devices"] as $device) {
                array_push($devices, $device);
            }
        }

        return $devices;
    }

    private function CreateOrUpdateDevice($device) {
        $deviceID = $device['DeviceID'];
        $deviceName = $device['DeviceName'];
        $serialNumber = $device['SerialNumber'];

        $category = $this->ReadPropertyInteger('Category');

        $instanceId = $this->FindBySerial($serialNumber);

        if($category && !$instanceId) {
            IPS_LogMessage("SymconMELCloud", "Creating device for serial number '$serialNumber'");
            $instanceId = IPS_CreateInstance('{1D2A6DC8-18ED-4206-831B-A87252E50F4C}');
            IPS_SetParent($instanceId, $category);
            IPS_SetProperty($instanceId, 'SerialNumber', $serialNumber);
        }

        if($instanceId) {
            IPS_LogMessage("SymconMELCloud", "Updating device with serial number '$serialNumber'");

            IPS_SetName($instanceId, $deviceName);

            IPS_SetProperty($instanceId, 'DeviceID', $deviceID);
            IPS_SetProperty($instanceId, 'DeviceName', $deviceName);

            IPS_ApplyChanges($instanceId);
        }
    }

    private function FindBySerial($serialNumber)
    {
        $ids = IPS_GetInstanceListByModuleID('{1D2A6DC8-18ED-4206-831B-A87252E50F4C}');
        $found = false;
        foreach ($ids as $id) {
            if (strtolower(IPS_GetProperty($id, 'SerialNumber')) == strtolower($serialNumber)) {
                $found = $id;
                break;
            }
        }
        return $found;
    }

    private function HasValidToken() {
        $token = $this->ReadPropertyString('Token');
        if ($token == '') {
            IPS_LogMessage("SymconMELCloud", "No token present");
            return false;
        }

        $tokenExpiryString = $this->ReadPropertyString('TokenExpiry');

        if ($tokenExpiryString == '') {
            IPS_LogMessage("SymconMELCloud", "Token expiry is unknown");
            return false;
        }

        $tz = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $tokenExpiry = strtotime($tokenExpiryString);
        date_default_timezone_set($tz);

        if($tokenExpiry == false) {
            IPS_LogMessage("SymconMELCloud", "Token expiry is not a valid date");
            return false;
        }

        if($tokenExpiry <= strtotime('-1 hour')) {
            IPS_LogMessage("SymconMELCloud", "Token is expired or will in the next hour");
            return false;
        }

        $url = "https://app.melcloud.com/Mitsubishi.Wifi.Client/User/ListDevices/";

        $headers = array();
        $headers[] = "Accept: application/json";
        $headers[] = "X-MitsContextKey: $token";

        $result = $this->Request($url, 'GET', array(), $headers);
        if($result == false) {
            IPS_LogMessage("SymconMELCloud", "Test call returned an error");
            return false;
        }

        IPS_LogMessage("SymconMELCloud", "Valid token was found");
        return true;
    }

    private function CreateToken()
    {
        $url = "https://app.melcloud.com/Mitsubishi.Wifi.Client/Login/ClientLogin";

        $params = array();
        $params['Email'] = $this->ReadPropertyString('Email');
        $params['password'] = $this->ReadPropertyString('Password');
        $params['AppVersion'] = "1.7.1.0";

        $headers = array();
        $headers[] = "Accept: application/json";

        IPS_LogMessage("SymconMELCloud", "Requesting a new token from '$url'");
        $result = $this->Request($url, 'POST', $params, $headers);

        if (isset($result["LoginData"]) && isset($result["LoginData"]["ContextKey"])) {
            IPS_SetProperty($this->InstanceID, 'Token', $result["LoginData"]["ContextKey"]);
            IPS_SetProperty($this->InstanceID, 'TokenExpiry', $result["LoginData"]["Expiry"]);
            IPS_ApplyChanges($this->InstanceID);
        }

        if($this->HasValidToken()) {
            IPS_LogMessage("SymconMELCloud", "Successfully acquired a new token from '$url'");
            $this->SetStatus(102);
        } else {
            IPS_LogMessage("SymconMELCloud", "Failed to acquire a new token from '$url'");
            $this->SetStatus(201);
        }
    }

    public function Request($url, $method, $params = array(), $headers = array())
    {
        $client = curl_init($url);
        curl_setopt($client, CURLOPT_CUSTOMREQUEST, $method);
//        curl_setopt($client, CURLOPT_SSL_VERIFYHOST, 0);
//        curl_setopt($client, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($client, CURLOPT_USERAGENT, 'SymconBotvac');
//        curl_setopt($client, CURLOPT_CONNECTTIMEOUT, 5);
//        curl_setopt($client, CURLOPT_TIMEOUT, 5);

        if ($method == 'POST') {
            curl_setopt($client, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        curl_setopt($client, CURLOPT_HTTPHEADER, $headers);

        ob_start();
        $out = fopen('php://output', 'w');
        curl_setopt($client, CURLOPT_VERBOSE, true);
        curl_setopt($client, CURLOPT_STDERR, $out);

        $result = curl_exec($client);
        $status = curl_getinfo($client, CURLINFO_HTTP_CODE);

        curl_close($client);

        fclose($out);
        $debug = ob_get_clean();
        IPS_LogMessage("SymconMELCloud", "Curl: $debug");

        if ($status == '0') {
            $this->SetStatus(201);
            return false;
        } elseif ($status != '200' && $status != '201') {
            IPS_LogMessage("SymconMELCloud", "Response invalid. Code $status");
            IPS_LogMessage("SymconMELCloud", "Response: '$result'");
            $this->SetStatus(201);
            return false;
        } else {
            IPS_LogMessage("SymconMELCloud", "Response: '$result'");
            return json_decode($result, true);
        }
    }
}
