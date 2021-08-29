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
     * Undocumented function
     *
     * @param array $namespaces Array of directories to lookup for Job classes
     */
    public function __construct(array $namespaces)
    {
        $this->namespaces = array_merge(
            $namespaces,
            [ "\\Dynamo\\Resque\\Jobs\\" ]
        );
    }

    public function parse(string $queue, $payload) : Job
    {
        $json = json_decode($payload, true);

        if(!$json || !isset($json['type'])){
            throw new Exception("Invalid JSON");
        }

        $job_type = $json['type'];
        $args = $json['args'] ?? null;

        if(strpos($job_type, ' ') !== false){
            throw new Exception("job type cannot contain spaces");
        }

        $lookup_places = [];

        foreach($this->lookup_dir as $dir){
            $path = implode(DIRECTORY_SEPARATOR, [
                rtrim($dir, DIRECTORY_SEPARATOR),
                trim($job_type)
            ]);
            $lookup_places[] = $path;
            $class = str_replace('/','\\',$path);
            if(class_exists($class)){
                return new $class($args);
            }
        }

        throw new Exception("Failed to find a job class for job type: '{$job_type}'. Tried: ".implode(', ', $lookup_places));
    }

    public function fromType(string $type, $args = null) : Job
    {
        $class = $this->getNamespacedJob($type);
        
        $job = new $class();
        $job->parseArgs($args);

        return $job;
    }

    protected function getNamespacedJob(string $type) : string
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
            $guess = $ns . str_replace(' ', '', ucwords(preg_replace('/[\s_-]+/',' ', $type)));
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
