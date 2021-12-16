<?php

namespace Dynamo\Resque\Jobs;

use Exception;

class DefaultJobFactory implements JobFactoryInterface
{
    /**
     * Namespaces to use when trying to instantiate a job
     *
     * @var array
     */
    protected $namespaces;

    /**
     * @param string|array $namespace Default namespace of your job classes,
     *    or an array of namespaces to look for the Job class
     */
    public function __construct($namespace)
    {
        $this->namespaces = (
            is_array($namespace) ?
            array_merge($namespace, [ "\\Dynamo\\Resque\\Jobs\\" ]) :
            [ $namespace, "\\Dynamo\\Resque\\Jobs\\" ]
        );
    }

    public function create(string $type, $args = null, $id = null) : Job
    {
        $class = $this->getJobClass($type);

        return new $class($args, $id);
    }

    public function decode(string $payload, string $queue) : Job
    {
        $json = json_decode($payload, true);

        if(!$json || !isset($json['type'])){
            throw new Exception("Invalid job wrapper. Payload MUST be a JSON object with 'type', and optionally 'args' and 'id'");
        }

        $job_type = $json['type'];
        $args = $json['args'] ?? null;
        $job_id = $json['id'] ?? null;

        if(strpos($job_type, ' ') !== false){
            throw new Exception("job type cannot contain spaces");
        }

        $class = $this->getJobClass($job_type);

        return new $class($args, $job_id, $queue);
    }

    public function encode(Job $job): string
    {
        $classMeta = new \ReflectionClass($job);
        return json_encode([
            'type' => $classMeta->getShortName(),
            'id' => $job->id,
            'args' => $job->getArgs(),
        ]);
        
    }

    protected function getJobClass(string $type) : string
    {
        $tried = [];
        foreach($this->namespaces as $ns){
            // try raw type, as provided
            $guess = $ns . $type;
            $tried[] = $guess;
            if(class_exists($guess)){
                return $guess;
            }

            // 2. try CamelCased version of provided type
            $guess = $ns . str_replace(' ', '', ucwords(preg_replace('/[\s_-]+/',' ', strtolower($type))));
            $tried[] = $guess;
            if(class_exists($guess)){
                return $guess;
            }

            // 3. Try Job suffix
            $guess .= 'Job';
            $tried[] = $guess;
            if(class_exists($guess)){
                return $guess;
            }
        }

        throw new Exception("Failed to find namespaced class for job type '{$type}'. Tried: " . implode(', ', $tried));
    }
}
