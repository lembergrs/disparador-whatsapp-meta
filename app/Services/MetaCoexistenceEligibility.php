<?php

namespace Services;

class MetaCoexistenceEligibility
{
    private $globallyEnabled;
    private $testClientIds;

    public function __construct($globallyEnabled, $testClientIds)
    {
        $this->globallyEnabled = $globallyEnabled === true;
        $this->testClientIds = self::normalizeTestClientIds($testClientIds);
    }

    public static function normalizeTestClientIds($value)
    {
        $ids = [];

        foreach(explode(',', (string) $value) as $entry){
            $entry = trim($entry);
            if($entry === '' || !preg_match('/^[1-9][0-9]*$/D', $entry)){
                continue;
            }

            $id = filter_var($entry, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1]
            ]);
            if($id !== false){
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    public function availableForClient($clientId)
    {
        if($this->globallyEnabled){
            return true;
        }

        if(!is_int($clientId) || $clientId <= 0){
            return false;
        }

        return in_array($clientId, $this->testClientIds, true);
    }
}
