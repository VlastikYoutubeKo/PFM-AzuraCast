<?php

declare(strict_types=1);

namespace App\Radio\Enums;

use App\Radio\Remote\AzuraRelay;
use App\Radio\Remote\Icecast;
use App\Radio\Remote\Shoutcast1;
use App\Radio\Remote\Shoutcast2;

enum RemoteAdapters: string implements AdapterTypeInterface
{
    case SHOUTcast1 = 'shoutcast1';
    case SHOUTcast2 = 'shoutcast2';
    case Icecast = 'icecast';
    case AzuraRelay = 'azurarelay';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getName(): string
    {
        return match ($this) {
            self::SHOUTcast1 => 'SHOUTcast 1',
            self::SHOUTcast2 => 'SHOUTcast 2',
            self::Icecast => 'Icecast',
            self::AzuraRelay => 'AzuraRelay',
        };
    }

    public function getClass(): string
    {
        return match ($this) {
            self::SHOUTcast1 => Shoutcast1::class,
            self::SHOUTcast2 => Shoutcast2::class,
            self::Icecast => Icecast::class,
            self::AzuraRelay => AzuraRelay::class,
        };
    }
}
