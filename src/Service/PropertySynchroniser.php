<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Service;

use DorsetDigital\Reapit\Model\Property;
use DorsetDigital\Reapit\Model\SyncState;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DB;

/**
 * Idempotent, resumable import; the checkpoint advances only after all pages succeed.
 */
class PropertySynchroniser
{
    use Injectable;

    public function __construct(
        private readonly ApiClient $api,
        private readonly PropertyMapper $mapper
    ) {
    }

    public function import(bool $full = false): int
    {
        $customer = $this->api->customerId();
        $state = SyncState::get()->filter(['CustomerID' => $customer, 'Channel' => 'properties'])->first();
        if (!$state) {
            $state = SyncState::create();
            $state->CustomerID = $customer;
            $state->Channel = 'properties';
        }
        $started = gmdate('Y-m-d H:i:s');
        $query = [
            'lettingStatus' => ['toLet', 'tenancyCurrent'],
            'internetAdvertising' => 'true',
        ];
        if (!$full && $state->LastSuccessful) {
            $query['modifiedFrom'] = gmdate('Y-m-d\TH:i:s\Z', strtotime($state->LastSuccessful) - 120);
        }

        $count = 0;
        foreach ($this->api->pages('/properties', $query) as $page) {
            foreach ($page as $remote) {
                if (!is_array($remote) || empty($remote['id'])) {
                    continue;
                }
                $property = Property::get()->filter([
                    'CustomerID' => $customer,
                    'ReapitID' => (string) $remote['id'],
                ])->first() ?: Property::create();

                $property->CustomerID = $customer;
                $property->ReapitID = (string) $remote['id'];
                $this->mapper->mapProperty($property, $remote);
                $property->write();
                $count++;
            }
        }
        $state->LastSuccessful = $started;
        $state->write();

        return $count;
    }

    /**
     * Re-fetch all currently marketable property IDs. Only remove stale local
     * records after the complete remote listing has been fetched successfully.
     */
    public function reconcile(): int
    {
        $customer = $this->api->customerId();
        $seen = [];
        foreach ($this->api->pages('/properties', [
            'lettingStatus' => ['toLet', 'tenancyCurrent'],
            'internetAdvertising' => 'true',
        ]) as $page) {
            foreach ($page as $remote) {
                if (is_array($remote) && isset($remote['id'])) {
                    $seen[(string) $remote['id']] = true;
                }
            }
        }
        $removed = 0;
        foreach (Property::get()->filter('CustomerID', $customer) as $property) {
            if (!isset($seen[$property->ReapitID])) {
                $property->Available = false;
                $property->write();
                $removed++;
            }
        }
        return $removed;
    }
}
