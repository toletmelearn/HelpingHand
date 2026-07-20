import os
import re
import json

# Paths
base_dir = r"c:\xampp\htdocs\HelpingHand"
models_dir = os.path.join(base_dir, "app", "Models")
services_dir = os.path.join(base_dir, "app", "Services")
controllers_dir = os.path.join(base_dir, "app", "Http", "Controllers")
migrations_dir = os.path.join(base_dir, "database", "migrations")
routes_dir = os.path.join(base_dir, "routes")
tests_dir = os.path.join(base_dir, "tests")

output_path = os.path.join(base_dir, "analyses", "architecture_baseline.txt")

# Helper to find files
def find_php_files(directory):
    results = []
    if not os.path.exists(directory):
        return results
    for root, dirs, files in os.walk(directory):
        for file in files:
            if file.endswith(".php"):
                results.append(os.path.join(root, file))
    return results

# 1. Model Relationship Inventory
def scan_models():
    models = {}
    files = find_php_files(models_dir)
    for file_path in files:
        with open(file_path, "r", encoding="utf-8", errors="ignore") as f:
            content = f.read()
        
        class_name = os.path.basename(file_path).replace(".php", "")
        
        # Table
        table_match = re.search(r'protected\s+\$table\s*=\s*[\'"]([^\'"]+)[\'"]', content)
        table = table_match.group(1) if table_match else f"Inferred ({class_name.lower()}s)"
        
        # Soft deletes
        soft_deletes = "SoftDeletes" in content
        
        # Relationships
        rels = []
        rel_matches = re.finditer(r'public\s+function\s+(\w+)\s*\([^\)]*\)\s*\{(.*?)\}', content, re.DOTALL)
        for m in rel_matches:
            name = m.group(1)
            body = m.group(2)
            if "belongsTo" in body:
                target = re.search(r'belongsTo\(\s*(\w+)::class', body)
                target_name = target.group(1) if target else "Unknown"
                rels.append({"type": "belongsTo", "name": name, "target": target_name})
            elif "hasMany" in body:
                target = re.search(r'hasMany\(\s*(\w+)::class', body)
                target_name = target.group(1) if target else "Unknown"
                rels.append({"type": "hasMany", "name": name, "target": target_name})
            elif "belongsToMany" in body:
                target = re.search(r'belongsToMany\(\s*(\w+)::class', body)
                target_name = target.group(1) if target else "Unknown"
                rels.append({"type": "belongsToMany", "name": name, "target": target_name})
            elif "hasOne" in body:
                target = re.search(r'hasOne\(\s*(\w+)::class', body)
                target_name = target.group(1) if target else "Unknown"
                rels.append({"type": "hasOne", "name": name, "target": target_name})

        models[class_name] = {
            "table": table,
            "soft_deletes": soft_deletes,
            "relationships": rels
        }
    return models

# 2. Service Dependency Graph
def scan_services():
    services = {}
    files = find_php_files(services_dir)
    for file_path in files:
        with open(file_path, "r", encoding="utf-8", errors="ignore") as f:
            content = f.read()
        
        class_name = os.path.basename(file_path).replace(".php", "")
        
        # Find dependencies in constructor
        deps = []
        constructor = re.search(r'public\s+function\s+__construct\s*\((.*?)\)', content, re.DOTALL)
        if constructor:
            args = constructor.group(1)
            # Find typed arguments
            typed_args = re.findall(r'(\w+)\s+\$\w+', args)
            deps = [d for d in typed_args if d not in ["Request", "string", "int", "array", "bool"]]
            
        services[class_name] = {
            "path": os.path.relpath(file_path, services_dir),
            "dependencies": deps
        }
    return services

# 3. Sidebar Entries
def parse_sidebar():
    sidebar_file = os.path.join(base_dir, "resources", "views", "layouts", "sidebar.blade.php")
    if not os.path.exists(sidebar_file):
        return []
    with open(sidebar_file, "r", encoding="utf-8", errors="ignore") as f:
        content = f.read()
    
    # We will look for structural blocks
    sections = re.findall(r'<!--\s*.*?(\d+)\.\s*(.*?)\s*-->', content)
    return sections

# Run Scans
models = scan_models()
services = scan_services()
sections = parse_sidebar()

# Output Results
with open(output_path, "w", encoding="utf-8") as out:
    out.write("=== ARCHITECTURAL METADATA ===\n")
    out.write(f"Models Scanned: {len(models)}\n")
    out.write(f"Services Scanned: {len(services)}\n")
    out.write("\n=== MODELS DETAIL ===\n")
    for name, m in sorted(models.items()):
        out.write(f"Model: {name} (table: {m['table']})\n")
        out.write(f"  SoftDeletes: {m['soft_deletes']}\n")
        for rel in m['relationships']:
            out.write(f"  Relation: {rel['name']} -> {rel['type']} -> {rel['target']}\n")
        out.write("-" * 45 + "\n")

    out.write("\n=== SERVICES DETAIL ===\n")
    for name, s in sorted(services.items()):
        out.write(f"Service: {name}\n")
        out.write(f"  Path: {s['path']}\n")
        out.write(f"  Dependencies: {s['dependencies']}\n")
        out.write("-" * 45 + "\n")

print(f"Metadata written to {output_path}")
