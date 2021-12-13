local job_type = KEYS[1]
local queue = KEYS[2]
local id = KEYS[3]
local id_counter_key = KEYS[4]
local job_info_key_prefix = KEYS[5]
local job_set_key = KEYS[6]
local args = ARGV[1]
local job = {}

if id == "*" then
    id = redis.call("INCR", id_counter_key)
end

local added = redis.call("SADD", job_set_key, id)
if added == 0 then
    return { 1 } -- error code: 1 - Job ID exists
end

local now = redis.call("TIME")[1]

-- set job info
redis.call("HMSET", job_info_key_prefix .. id,
    "id", id,
    "type", job_type,
    "args", args,
    "created", now
)

job["id"] = id
job["type"] = job_type
job["args"] = args

redis.call("RPUSH", queue, cjson.encode(job))

return {
    0,
    job["id"]
}
