<?php
declare(strict_types=1);

namespace AttributeRegistry\Enum;

enum AttributeTargetType: string
{
    case CLASS_TYPE = 'class';
    case METHOD = 'method';
    case PROPERTY = 'property';
    case PARAMETER = 'parameter';
    case CONSTANT = 'constant';
}
