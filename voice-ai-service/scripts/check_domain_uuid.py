#!/usr/bin/env python3
"""
Script de validação multi-tenant para Voice AI IVR.

Verifica se todos os arquivos Python seguem o padrão multi-tenant,
garantindo que domain_uuid seja usado corretamente.

Uso:
    python scripts/check_domain_uuid.py
    python scripts/check_domain_uuid.py --fix  # Mostra sugestões de correção
"""

import os
import re
import sys
from pathlib import Path
from typing import List, Tuple


# Padrões a verificar
PATTERNS = {
    # Endpoints sem domain_uuid
    "endpoint_without_domain": {
        "pattern": r"@router\.(get|post|put|delete|patch)\([^)]+\)\s*\n\s*async def \w+\([^)]*\)(?!.*domain_uuid)",
        "message": "Endpoint pode não exigir domain_uuid",
        "severity": "warning",
    },
    # Query SQL sem WHERE domain_uuid
    "sql_without_domain": {
        "pattern": r"SELECT\s+.*\s+FROM\s+v_voice_\w+(?!\s+WHERE[^;]*domain_uuid)",
        "message": "Query SQL pode não filtrar por domain_uuid",
        "severity": "error",
    },
    # INSERT sem domain_uuid
    "insert_without_domain": {
        "pattern": r"INSERT\s+INTO\s+v_voice_\w+\s*\([^)]+\)(?!.*domain_uuid)",
        "message": "INSERT pode não incluir domain_uuid",
        "severity": "error",
    },
    # Função que não valida domain_uuid
    "function_without_validation": {
        "pattern": r"async def \w+\([^)]*domain_uuid[^)]*\):[^}]*(?!if not domain_uuid)",
        "message": "Função recebe domain_uuid mas pode não validar",
        "severity": "warning",
    },
}

# Arquivos/diretórios a ignorar
IGNORE_PATHS = [
    "__pycache__",
    ".git",
    "venv",
    "node_modules",
    ".pytest_cache",
    "tests",
]


def should_ignore(path: Path) -> bool:
    """Verifica se o caminho deve ser ignorado."""
    for ignore in IGNORE_PATHS:
        if ignore in str(path):
            return True
    return False


def check_file(file_path: Path) -> List[Tuple[str, int, str, str]]:
    """
    Verifica um arquivo Python por violações multi-tenant.
    
    Returns:
        Lista de (arquivo, linha, severidade, mensagem)
    """
    issues = []
    
    try:
        content = file_path.read_text(encoding="utf-8")
    except Exception as e:
        return [(str(file_path), 0, "error", f"Não foi possível ler: {e}")]
    
    lines = content.split("\n")
    
    for pattern_name, pattern_info in PATTERNS.items():
        matches = re.finditer(pattern_info["pattern"], content, re.IGNORECASE | re.MULTILINE)
        
        for match in matches:
            # Encontrar número da linha
            line_start = content[:match.start()].count("\n") + 1
            
            issues.append((
                str(file_path),
                line_start,
                pattern_info["severity"],
                f"[{pattern_name}] {pattern_info['message']}"
            ))
    
    # Verificações específicas
    
    # Verificar se BaseRequest é usada em request models
    if "class" in content and "Request" in content and "BaseModel" in content:
        if "BaseRequest" not in content and "domain_uuid" not in content:
            issues.append((
                str(file_path),
                1,
                "warning",
                "Request model pode não herdar de BaseRequest (sem domain_uuid)"
            ))
    
    # Verificar se endpoints retornam erro 400 para domain_uuid ausente
    if "@router." in content and "domain_uuid" in content:
        if "HTTPException" not in content and "400" not in content:
            issues.append((
                str(file_path),
                1,
                "warning",
                "Endpoint pode não retornar erro 400 para domain_uuid ausente"
            ))
    
    return issues


def main():
    """Função principal."""
    show_fix = "--fix" in sys.argv
    
    # Encontrar raiz do projeto
    script_dir = Path(__file__).parent
    project_root = script_dir.parent
    
    print("🔍 Verificando padrão multi-tenant...")
    print(f"📂 Diretório: {project_root}\n")
    
    all_issues = []
    files_checked = 0
    
    # Verificar todos os arquivos Python
    for py_file in project_root.rglob("*.py"):
        if should_ignore(py_file):
            continue
        
        files_checked += 1
        issues = check_file(py_file)
        all_issues.extend(issues)
    
    # Exibir resultados
    if all_issues:
        print(f"⚠️  Encontrados {len(all_issues)} potenciais problemas:\n")
        
        errors = [i for i in all_issues if i[2] == "error"]
        warnings = [i for i in all_issues if i[2] == "warning"]
        
        if errors:
            print("🔴 ERROS (devem ser corrigidos):")
            for file_path, line, severity, message in errors:
                rel_path = Path(file_path).relative_to(project_root)
                print(f"  {rel_path}:{line}: {message}")
            print()
        
        if warnings:
            print("🟡 AVISOS (revisar manualmente):")
            for file_path, line, severity, message in warnings:
                rel_path = Path(file_path).relative_to(project_root)
                print(f"  {rel_path}:{line}: {message}")
            print()
        
        if show_fix:
            print("💡 Sugestões de correção:")
            print("  1. Certifique-se que todos os Request models herdam de BaseRequest")
            print("  2. Adicione validação: if not request.domain_uuid: raise HTTPException(400, ...)")
            print("  3. Inclua domain_uuid em todas as queries SQL")
            print("  4. Verifique se ProviderManager recebe domain_uuid")
        
        # Retornar código de erro se houver erros
        if errors:
            sys.exit(1)
    else:
        print(f"✅ Nenhum problema encontrado em {files_checked} arquivos!")
    
    print(f"\n📊 Resumo: {files_checked} arquivos verificados, {len(all_issues)} problemas encontrados")


if __name__ == "__main__":
    main()
