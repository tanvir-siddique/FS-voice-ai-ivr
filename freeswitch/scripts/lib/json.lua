--[[
JSON Library for Lua
Simple JSON encoder/decoder

Nota: Em produção, use cjson ou dkjson para melhor performance.
Esta é uma implementação simplificada.
]]--

local json = {}

-- Encode Lua table to JSON string
function json.encode(obj)
    local t = type(obj)
    
    if t == "nil" then
        return "null"
    elseif t == "boolean" then
        return obj and "true" or "false"
    elseif t == "number" then
        return tostring(obj)
    elseif t == "string" then
        -- Escapar caracteres especiais
        obj = obj:gsub('\\', '\\\\')
        obj = obj:gsub('"', '\\"')
        obj = obj:gsub('\n', '\\n')
        obj = obj:gsub('\r', '\\r')
        obj = obj:gsub('\t', '\\t')
        return '"' .. obj .. '"'
    elseif t == "table" then
        -- Verificar se é array ou object
        local is_array = true
        local max_index = 0
        
        for k, v in pairs(obj) do
            if type(k) ~= "number" then
                is_array = false
                break
            end
            if k > max_index then
                max_index = k
            end
        end
        
        if is_array and max_index == #obj then
            -- Array
            local parts = {}
            for i, v in ipairs(obj) do
                table.insert(parts, json.encode(v))
            end
            return "[" .. table.concat(parts, ",") .. "]"
        else
            -- Object
            local parts = {}
            for k, v in pairs(obj) do
                table.insert(parts, '"' .. tostring(k) .. '":' .. json.encode(v))
            end
            return "{" .. table.concat(parts, ",") .. "}"
        end
    else
        error("Cannot encode type: " .. t)
    end
end

-- Decode JSON string to Lua table
-- Nota: Implementação simplificada, use cjson em produção
function json.decode(str)
    if not str or str == "" then
        return nil
    end
    
    -- Usar loadstring para parsing simples (CUIDADO: não seguro para dados não confiáveis!)
    -- Em produção, use uma biblioteca JSON adequada como cjson ou dkjson
    
    -- Converter JSON para sintaxe Lua
    local lua_str = str
    lua_str = lua_str:gsub('null', 'nil')
    lua_str = lua_str:gsub('true', 'true')
    lua_str = lua_str:gsub('false', 'false')
    lua_str = lua_str:gsub('(%[)', '{')
    lua_str = lua_str:gsub('(%])', '}')
    lua_str = lua_str:gsub('"([^"]+)"%s*:', '["%1"]=')
    
    -- Tentar carregar como código Lua
    local func, err = loadstring("return " .. lua_str)
    if func then
        local ok, result = pcall(func)
        if ok then
            return result
        end
    end
    
    -- Fallback: retornar nil se falhar
    return nil
end

return json
