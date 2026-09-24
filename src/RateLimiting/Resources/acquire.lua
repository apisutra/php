-- Все проверки и вычисления выполняются до единственной записи счётчиков.
local maxInt = 9007199254740991
local function integer(text)
    local value = tonumber(text)
    if not value or value < 0 or value > maxInt or value ~= math.floor(value) then return nil end
    if string.format('%.0f', value) ~= text then return nil end
    return value
end
local time = redis.call('TIME')
local now = tonumber(time[1]) * 1000 + math.floor(tonumber(time[2]) / 1000)
if #ARGV ~= #KEYS * 2 then return {-2} end
local writes = {}
local resets = {}
local blocked = {}
local wait = 0
for i, key in ipairs(KEYS) do
    local limit = integer(ARGV[i * 2 - 1])
    local period = integer(ARGV[i * 2])
    if not limit or limit < 1 or not period or period < 1 or now > maxInt - period then return {-3} end
    local count = 0
    local reset = now + period
    local raw = redis.call('GET', key)
    if raw then
        local l, p, c, r = string.match(raw, '^v1|(%d+)|(%d+)|(%d+)|(%d+)$')
        l, p, c, r = integer(l), integer(p), integer(c), integer(r)
        if not l or l < 1 or not p or p < 1 or not c or c > l or not r then return {-2} end
        if r > now then
            if l ~= limit or p ~= period then return {-1} end
            count, reset = c, r
        end
    end
    if count >= limit then
        table.insert(blocked, i)
        wait = math.max(wait, reset - now)
    end
    table.insert(writes, key)
    local nextCount = count < limit and count + 1 or count
    table.insert(writes, string.format('v1|%.0f|%.0f|%.0f|%.0f', limit, period, nextCount, reset))
    resets[i] = string.format('%.0f', reset)
end
if #blocked > 0 then
    local reply = {0, wait}
    for _, index in ipairs(blocked) do table.insert(reply, index) end
    return reply
end
if #KEYS > 0 then
    redis.call('MSET', unpack(writes))
    for i, key in ipairs(KEYS) do redis.call('PEXPIREAT', key, resets[i]) end
end
return {1}
