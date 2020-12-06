<?php

namespace Dynamo\Resque\Redis;

/**
 * @method self del(string $key)
 * @method self hIncrBy(string $key, string $field, int $by)
 * @method self sRem(string $key, string ...$member) Removes the specified member from the set value stored at key.
 * @method array exec()
 */
interface PipelineInterface
{

}