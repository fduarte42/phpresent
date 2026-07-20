<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Domain\ValueObject;

enum DisplayRole: string
{
    case Main = 'main';
    case Operator = 'operator';
    case ConfidenceMonitor = 'confidence_monitor';
    case Audience = 'audience';
    case Custom = 'custom';
}
