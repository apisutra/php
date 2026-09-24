-- Атомарный максимум относительного срока; неизвестное состояние не означает допуск.
local delay = tonumber(ARGV[1])
if not delay or delay < 0 or delay ~= math.floor(delay) or delay > 9007199254740991 then
    return redis.error_reply('INVALID_COOLDOWN_DURATION')
end
local remaining = redis.call('PTTL', KEYS[1])
if remaining == -1 or remaining > 9007199254740991 then
    return redis.error_reply('INVALID_COOLDOWN_TTL')
end
remaining = math.max(0, remaining)
if delay <= remaining then
    return {0, remaining}
end
redis.call('SET', KEYS[1], '1', 'PX', ARGV[1])
return {1, delay}
