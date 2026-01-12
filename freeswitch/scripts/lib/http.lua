--[[
HTTP Client Library for FreeSWITCH Lua
Simple HTTP client using luasocket or curl

⚠️ Nota: Esta é uma implementação básica. Em produção, considere usar luasocket ou mod_curl.
]]--

local http = {}

-- Usar curl como fallback (mais comum em servidores)
local function curl_request(method, url, body, headers)
    local cmd = "curl -s -X " .. method
    
    -- Adicionar headers
    headers = headers or {}
    headers["Content-Type"] = headers["Content-Type"] or "application/json"
    
    for k, v in pairs(headers) do
        cmd = cmd .. ' -H "' .. k .. ': ' .. v .. '"'
    end
    
    -- Adicionar body
    if body then
        -- Escapar aspas no body
        body = body:gsub('"', '\\"')
        cmd = cmd .. " -d '" .. body .. "'"
    end
    
    cmd = cmd .. ' -w "\\n%{http_code}" "' .. url .. '"'
    
    -- Executar curl
    local handle = io.popen(cmd)
    if not handle then
        return nil
    end
    
    local output = handle:read("*a")
    handle:close()
    
    -- Separar body e status code
    local lines = {}
    for line in output:gmatch("[^\r\n]+") do
        table.insert(lines, line)
    end
    
    local status_code = tonumber(lines[#lines]) or 0
    table.remove(lines)
    local response_body = table.concat(lines, "\n")
    
    return {
        status = status_code,
        body = response_body,
    }
end

function http.get(url, headers)
    return curl_request("GET", url, nil, headers)
end

function http.post(url, body, headers)
    return curl_request("POST", url, body, headers)
end

function http.put(url, body, headers)
    return curl_request("PUT", url, body, headers)
end

function http.delete(url, headers)
    return curl_request("DELETE", url, nil, headers)
end

return http
