<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Plan;

/** @internal Статическое назначение поля; URL-плейсхолдер уточняется при исполнении. */
enum RequestFieldTarget
{
    case Skip;
    case BodyRoot;
    case File;
    case Header;
    case Path;
    case Body;
    case Query;
    case Unmapped;
}
