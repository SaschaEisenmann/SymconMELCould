<?php

class MELCloudDevice extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('DeviceID', '');
        $this->RegisterPropertyString('BuildingID', '');
        $this->RegisterPropertyString('DeviceName', '');
        $this->RegisterPropertyString('SerialNumber', '');


        $this->RegisterPropertyString('Email', '');
        $this->RegisterPropertyString('Password', '');

        $this->RegisterPropertyString('Token', '');
        $this->RegisterPropertyString('TokenExpiry', '');

        $this->RegisterPropertyInteger('UpdateInterval', 120);

        $this->RegisterTimer('Update', 60000, 'MCD_Update($_IPS[\'TARGET\'], 0);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        if (IPS_VariableProfileExists("MCD_Mode")) {
            IPS_DeleteVariableProfile("MCD_Mode");
        }


        IPS_CreateVariableProfile("MCD_Mode", 1);
        IPS_SetVariableProfileAssociation("MCD_Mode", 0, "Off", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_Mode", 1, "Heizen", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_Mode", 3, "Kühlen", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_Mode", 2, "Trocken", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_Mode", 7, "Lüfter", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_Mode", 8, "Auto", "", "-1");


        if (IPS_VariableProfileExists("MCD_FanSpeed")) {
            IPS_DeleteVariableProfile("MCD_FanSpeed");
        }


        IPS_CreateVariableProfile("MCD_FanSpeed", 1);
        IPS_SetVariableProfileValues("MCD_FanSpeed", 0, 5, 1);
        IPS_SetVariableProfileAssociation("MCD_FanSpeed", 0, "Auto", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_FanSpeed", 1, "1", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_FanSpeed", 2, "2", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_FanSpeed", 3, "3", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_FanSpeed", 4, "4", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_FanSpeed", 5, "5", "", "-1");


        if (IPS_VariableProfileExists("MCD_HorizontalFanPosition")) {
            IPS_DeleteVariableProfile("MCD_HorizontalFanPosition");
        }
        IPS_CreateVariableProfile("MCD_HorizontalFanPosition", 1);
        IPS_SetVariableProfileAssociation("MCD_HorizontalFanPosition", 0, "Auto", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_HorizontalFanPosition", 1, "Left", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_HorizontalFanPosition", 2, "CenterLeft", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_HorizontalFanPosition", 3, "Center", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_HorizontalFanPosition", 4, "CenterRight", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_HorizontalFanPosition", 5, "Right", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_HorizontalFanPosition", 8, "LeftAndRight", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_HorizontalFanPosition", 12, "Swing", "", "-1");

        if (IPS_VariableProfileExists("MCD_VerticalFanPosition")) {
            IPS_DeleteVariableProfile("MCD_VerticalFanPosition");
        }
        IPS_CreateVariableProfile("MCD_VerticalFanPosition", 1);
        IPS_SetVariableProfileAssociation("MCD_VerticalFanPosition", 0, "Auto", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_VerticalFanPosition", 1, "Top", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_VerticalFanPosition", 2, "CenterTop", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_VerticalFanPosition", 3, "Center", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_VerticalFanPosition", 4, "CenterBottom", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_VerticalFanPosition", 5, "Bottom", "", "-1");
        IPS_SetVariableProfileAssociation("MCD_VerticalFanPosition", 7, "Swing", "", "-1");


        $this->RegisterVariableInteger('FAN_SPEED', 'FanSpeed', "MCD_FanSpeed", 0);
        $this->EnableAction("FAN_SPEED");

        $this->SetTimerInterval('Update', $this->ReadPropertyInteger('UpdateInterval') * 1000);

        $this->RegisterVariableBoolean('POWER', 'Power', '~Switch', 0);
        $this->EnableAction("POWER");

        $this->RegisterVariableInteger('MODE', 'Mode', "MCD_Mode", 0);
        $this->EnableAction("MODE");

        $this->RegisterVariableInteger('HORIZONTAL_FAN_POSITION', 'HorizontalFanPosition', "MCD_HorizontalFanPosition", 0);
        $this->EnableAction("HORIZONTAL_FAN_POSITION");

        $this->RegisterVariableInteger('VERTICAL_FAN_POSITION', 'VerticalFanPosition', "MCD_VerticalFanPosition", 0);
        $this->EnableAction("VERTICAL_FAN_POSITION");

        // 0 -> Off
        // 1 -> SWW
        // 2 -> Heating
        // 3 -> Cooling
        // 4 -> Defrost
        // 5 -> Standby
        // 6 -> Legionella


        $this->RegisterVariableFloat('ROOM_TEMPERATURE', 'RoomTemperature', '~Temperature', 3);

        $this->RegisterVariableFloat('SET_TEMPERATURE', 'SetTemperature', '~Temperature', 4);
        $this->EnableAction("SET_TEMPERATURE");

        $this->Update();
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case "POWER":
                $this->UpdatePower($Value);
                break;
            case "SET_TEMPERATURE":
                $this->UpdateSetTemperature($Value);
                break;
            case "MODE":
                $this->UpdateMode($Value);
                break;
            case "FAN_SPEED":
                $this->UpdateFanSpeed($Value);
                break;
            case "HORIZONTAL_FAN_POSITION":
                $this->UpdateHorizontalFanPosition($Value);
                break;
            case "VERTICAL_FAN_POSITION":
                $this->UpdateVerticalFanPosition($Value);
                break;
        }
    }

    public function UpdatePower($power)
    {
        if (!$this->HasValidToken()) {
            $this->CreateToken();
        }

        $token = $this->ReadPropertyString('Token');
        $url = "https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta";

        $headers = array();
        $headers[] = "Accept: application/json";
        $headers[] = "X-MitsContextKey: $token";

        $params = array();

        if ($power) {
            $params['Power'] = "true";
        } else {
            $params['Power'] = "false";
        }
        $params['DeviceID'] = $this->ReadPropertyString('DeviceID');
        $params['EffectiveFlags'] = "1";
        $params['HasPendingCommand'] = "true";

        $response = $this->Request($url, "POST", $params, $headers);

        if (isset($response["HasPendingCommand"])) {
            $this->UpdateFromStatus($response, "POWER");
        }
    }

    public function UpdateSetTemperature($temperature)
    {
        if (!$this->HasValidToken()) {
            $this->CreateToken();
        }

        $token = $this->ReadPropertyString('Token');
        $url = "https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta";

        $headers = array();
        $headers[] = "Accept: application/json";
        $headers[] = "X-MitsContextKey: $token";

        $params = array();
        $params['SetTemperature'] = $temperature;
        $params['DeviceID'] = $this->ReadPropertyString('DeviceID');
        $params['EffectiveFlags'] = "4";
        $params['HasPendingCommand'] = "true";

        $response = $this->Request($url, "POST", $params, $headers);

        if (isset($response["HasPendingCommand"])) {
            $this->UpdateFromStatus($response, "SET_TEMPERATURE");
        }
    }

    public function UpdateMode($mode)
    {
        if (!$this->HasValidToken()) {
            $this->CreateToken();
        }

        $token = $this->ReadPropertyString('Token');
        $url = "https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta";

        $headers = array();
        $headers[] = "Accept: application/json";
        $headers[] = "X-MitsContextKey: $token";

        $params = array();

        $params['OperationMode'] = $mode;
        $params['DeviceID'] = $this->ReadPropertyString('DeviceID');
        $params['EffectiveFlags'] = "2";
        $params['HasPendingCommand'] = "true";

        $response = $this->Request($url, "POST", $params, $headers);

        if (isset($response["HasPendingCommand"])) {
            $this->UpdateFromStatus($response, "MODE");
        }
    }

    public function UpdateFanSpeed($speed)
    {
        if (!$this->HasValidToken()) {
            $this->CreateToken();
        }

        $token = $this->ReadPropertyString('Token');
        $url = "https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta";

        $headers = array();
        $headers[] = "Accept: application/json";
        $headers[] = "X-MitsContextKey: $token";

        $params = array();

        $params['SetFanSpeed'] = $speed;
        $params['DeviceID'] = $this->ReadPropertyString('DeviceID');
        $params['EffectiveFlags'] = "8";
        //$params['HasPendingCommand'] = "true";

        $response = $this->Request($url, "POST", $params, $headers);

        if (isset($response["HasPendingCommand"])) {
            $this->UpdateFromStatus($response, "FAN_SPEED");
        }
    }

    public function UpdateHorizontalFanPosition($position)
    {
        if (!$this->HasValidToken()) {
            $this->CreateToken();
        }

        $token = $this->ReadPropertyString('Token');
        $url = "https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta";

        $headers = array();
        $headers[] = "Accept: application/json";
        $headers[] = "X-MitsContextKey: $token";

        $params = array();

        $params['VaneHorizontal'] = $position;
        $params['DeviceID'] = $this->ReadPropertyString('DeviceID');
        $params['EffectiveFlags'] = "16";
        $params['HasPendingCommand'] = "true";

        $response = $this->Request($url, "POST", $params, $headers);

        if (isset($response["HasPendingCommand"])) {
            $this->UpdateFromStatus($response, "HORIZONTAL_FAN_POSITION");
        }
    }

    public function UpdateVerticalFanPosition($position)
    {
        if (!$this->HasValidToken()) {
            $this->CreateToken();
        }

        $token = $this->ReadPropertyString('Token');
        $url = "https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta";

        $headers = array();
        $headers[] = "Accept: application/json";
        $headers[] = "X-MitsContextKey: $token";

        $params = array();

        $params['VaneVertical'] = $position;
        $params['DeviceID'] = $this->ReadPropertyString('DeviceID');
        $params['EffectiveFlags'] = "272280";
        $params['HasPendingCommand'] = "true";

        $response = $this->Request($url, "POST", $params, $headers);

        if (isset($response["HasPendingCommand"])) {
            $this->UpdateFromStatus($response, "VERTICAL_FAN_POSITION");
        }
    }

    public function Set(bool $power, int $mode, int $temperature, int $fanSpeed, int $horizontalFanPosition, int $verticalFanPosition)
    {
        if($power == null) {
            $power = GetValueBoolean($this->GetIDForIdent("POWER"));
        }

        if($mode == null) {
            $mode = GetValueInteger($this->GetIDForIdent("MODE"));
        }

        if($temperature == null) {
            $temperature = GetValueInteger($this->GetIDForIdent("SET_TEMPERATURE"));
        }

        if($fanSpeed == null) {
            $fanSpeed = GetValueInteger($this->GetIDForIdent("FAN_SPEED"));
        }

        if($horizontalFanPosition == null) {
            $horizontalFanPosition = GetValueInteger($this->GetIDForIdent("HORIZONTAL_FAN_POSITION"));
        }

        if($verticalFanPosition == null) {
            $verticalFanPosition = GetValueInteger($this->GetIDForIdent("VERTICAL_FAN_POSITION"));
        }

        if (!$this->HasValidToken()) {
            $this->CreateToken();
        }

        $token = $this->ReadPropertyString('Token');
        $url = "https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/SetAta";

        $headers = array();
        $headers[] = "Accept: application/json";
        $headers[] = "X-MitsContextKey: $token";

        $params = array();
        $params['DeviceID'] = $this->ReadPropertyString('DeviceID');
        $params['EffectiveFlags'] = "287";
        $params['HasPendingCommand'] = "true";

        $params['Power'] = $power;
        $params['OperationMode'] = $mode;
        $params['SetTemperature'] = $temperature;
        $params['SetFanSpeed'] = $fanSpeed;
        $params['VaneHorizontal'] = $horizontalFanPosition;
        $params['VaneVertical'] = $verticalFanPosition;

        $response = $this->Request($url, "POST", $params, $headers);

        if (isset($response["HasPendingCommand"])) {
            $this->UpdateFromStatus($response, null);
        }
    }

    public function Update()
    {
        $status = $this->RequestStatus();

        $this->UpdateFromStatus($status);
    }

    private function UpdateFromStatus($status, $for = null)
    {
        IPS_LogMessage("SymconMELCloud", json_encode($status));

        if (!$for || $for == "POWER") {
            $power = $status['Power'];
            SetValueBoolean($this->GetIDForIdent("POWER"), $power);
            IPS_SetHidden($this->GetIDForIdent('MODE'), !$power);
            IPS_SetHidden($this->GetIDForIdent('ROOM_TEMPERATURE'), !$power);
            IPS_SetHidden($this->GetIDForIdent('SET_TEMPERATURE'), !$power);
            IPS_SetHidden($this->GetIDForIdent('FAN_SPEED'), !$power);
            IPS_SetHidden($this->GetIDForIdent('VERTICAL_FAN_POSITION'), !$power);
            IPS_SetHidden($this->GetIDForIdent('HORIZONTAL_FAN_POSITION'), !$power);
        }

        if (!$for || $for == "MODE") {
            SetValueInteger($this->GetIDForIdent("MODE"), $status["OperationMode"]);
        }

        SetValueFloat($this->GetIDForIdent("ROOM_TEMPERATURE"), $status['RoomTemperature']);

        if (!$for || $for == "SET_TEMPERATURE") {
            SetValueFloat($this->GetIDForIdent("SET_TEMPERATURE"), $status['SetTemperature']);
        }

        if (!$for || $for == "FAN_SPEED") {
            SetValueInteger($this->GetIDForIdent("FAN_SPEED"), $status['SetFanSpeed']);
        }

        if (!$for || $for == "VERTICAL_FAN_POSITION") {
            SetValueInteger($this->GetIDForIdent("VERTICAL_FAN_POSITION"), $status['VaneVertical']);
        }

        if (!$for || $for == "HORIZONTAL_FAN_POSITION") {
            SetValueInteger($this->GetIDForIdent("HORIZONTAL_FAN_POSITION"), $status['VaneHorizontal']);
        }
    }

    private function RequestStatus()
    {
        if (!$this->HasValidToken()) {
            $this->CreateToken();
        }

        $token = $this->ReadPropertyString('Token');

        IPS_LogMessage("SymconMELCloud", $token);

        $deviceID = $this->ReadPropertyString('DeviceID');
        $buildingID = $this->ReadPropertyString('BuildingID');

        $url = "https://app.melcloud.com/Mitsubishi.Wifi.Client/Device/Get?id=$deviceID&buildingID=$buildingID";

        $headers = array();
        $headers[] = "Accept: application/json";
        $headers[] = "X-MitsContextKey: $token";

        IPS_LogMessage("SymconMELCloud", "Requesting status from '$url'");
        $result = $this->Request($url, 'GET', array(), $headers);

        return $result;
    }

    private function HasValidToken()
    {
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

        if ($tokenExpiry == false) {
            IPS_LogMessage("SymconMELCloud", "Token expiry is not a valid date");
            return false;
        }

        if ($tokenExpiry <= strtotime('-1 hour')) {
            IPS_LogMessage("SymconMELCloud", "Token is expired or will in the next hour");
            return false;
        }

        $url = "https://app.melcloud.com/Mitsubishi.Wifi.Client/User/ListDevices/";

        $headers = array();
        $headers[] = "Accept: application/json";
        $headers[] = "X-MitsContextKey: $token";

        $result = $this->Request($url, 'GET', array(), $headers);
        if ($result == false) {
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

        if ($this->HasValidToken()) {
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
        curl_setopt($client, CURLOPT_TIMEOUT, 5);

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
