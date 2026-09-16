<?php

namespace Paymob\Laravel\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Paymob\Laravel\Concerns\HasPaymobCapture;

/**
 * Static-analysis fixture proving the package trait against its documented host shape.
 */
final class UsesHasPaymobCapture extends Model
{
    use HasPaymobCapture;
}
