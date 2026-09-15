<?php

namespace App\Support;

use App\Models\Person;

class PersonShareCode
{
    public function for(Person $person): string
    {
        $id = (string) $person->getKey();
        $signature = substr(hash_hmac('sha256', 'person:'.$id, (string) config('app.key')), 0, 16);

        return 'TBK-P-'.$id.'-'.$signature;
    }

    public function resolve(string $code): ?Person
    {
        if (! preg_match('/^TBK-P-(\d+)-([a-f0-9]{16})$/i', trim($code), $matches)) {
            return null;
        }

        $id = $matches[1];
        $expected = substr(hash_hmac('sha256', 'person:'.$id, (string) config('app.key')), 0, 16);

        if (! hash_equals($expected, strtolower($matches[2]))) {
            return null;
        }

        return Person::query()
            ->with(['marga:id,name', 'father.marga:id,name'])
            ->find((int) $id);
    }
}
