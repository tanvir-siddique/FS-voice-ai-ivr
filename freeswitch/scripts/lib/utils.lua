--[[
Utility functions for Voice AI Secretary
]]--

local utils = {}

-- Gerar UUID v4
function utils.uuid()
    local template = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'
    return string.gsub(template, '[xy]', function(c)
        local v = (c == 'x') and math.random(0, 15) or math.random(8, 11)
        return string.format('%x', v)
    end)
end

-- Obter timestamp ISO 8601
function utils.timestamp()
    return os.date("!%Y-%m-%dT%H:%M:%SZ")
end

-- Sanitizar string para uso em SQL (CUIDADO: use prepared statements quando possível)
function utils.sanitize(str)
    if not str then return "" end
    return str:gsub("'", "''")
end

-- Verificar se arquivo existe
function utils.file_exists(path)
    local file = io.open(path, "r")
    if file then
        file:close()
        return true
    end
    return false
end

-- Criar diretório se não existir
function utils.ensure_dir(path)
    os.execute("mkdir -p " .. path)
end

-- Limpar arquivos temporários antigos
function utils.cleanup_temp(dir, max_age_seconds)
    max_age_seconds = max_age_seconds or 3600  -- 1 hora por padrão
    local now = os.time()
    
    local handle = io.popen('find "' .. dir .. '" -type f -mmin +' .. math.floor(max_age_seconds / 60))
    if handle then
        for file in handle:lines() do
            os.remove(file)
        end
        handle:close()
    end
end

-- Formatar número de telefone
function utils.format_phone(number)
    if not number then return "" end
    
    -- Remover caracteres não numéricos
    number = number:gsub("%D", "")
    
    -- Formatar para Brasil (exemplo)
    if #number == 11 then
        return string.format("(%s) %s-%s", 
            number:sub(1, 2), 
            number:sub(3, 7), 
            number:sub(8, 11))
    elseif #number == 10 then
        return string.format("(%s) %s-%s", 
            number:sub(1, 2), 
            number:sub(3, 6), 
            number:sub(7, 10))
    end
    
    return number
end

return utils
