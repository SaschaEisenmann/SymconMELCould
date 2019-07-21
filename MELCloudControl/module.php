<?php
class MELCloudControl extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString('Email', '');
        $this->RegisterPropertyString('Password', '');

        $this->RegisterPropertyString('Token', '');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
    }

    public function Sync()
    {
    }
}
