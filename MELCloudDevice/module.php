<?php
class MELCloudDevice extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('DeviceID', '');
        $this->RegisterPropertyString('DeviceName', '');
        $this->RegisterPropertyString('SerialNumber', '');
        $this->RegisterPropertyInteger('UpdateInterval', 120);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
    }
}
