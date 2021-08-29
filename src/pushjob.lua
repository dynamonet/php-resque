local job_type = KEYS[1]
local queue = KEYS[2]
local counter = KEYS[3]
local args = ARGV[1]
local job = {}

job["id"] = redis.call("INCR", counter)
job["type"] = job_type
job["args"] = args

redis.call("RPUSH", queue, cjson.encode(job))

return job["id"]
