import os
import re

controllers_dir = r"c:\xampp\htdocs\HelpingHand\app\Http\Controllers"
output_file = r"c:\xampp\htdocs\HelpingHand\analyses\controller_summary.txt"

if not os.path.exists(controllers_dir):
    print(f"Directory not found: {controllers_dir}")
    exit(1)

def scan_dir(path):
    results = []
    for root, dirs, files in os.walk(path):
        for file in files:
            if file.endswith(".php"):
                results.append(os.path.join(root, file))
    return results

controller_files = scan_dir(controllers_dir)

with open(output_file, "w", encoding="utf-8") as out:
    out.write(f"CONTROLLER SCANNER SUMMARY\n")
    out.write(f"Total controller files found: {len(controller_files)}\n")
    out.write("="*80 + "\n\n")

    for file_path in sorted(controller_files):
        with open(file_path, "r", encoding="utf-8", errors="ignore") as f:
            content = f.read()

        rel_path = os.path.relpath(file_path, controllers_dir)
        class_name = os.path.basename(file_path).replace(".php", "")
        
        # Namespace
        ns_match = re.search(r'namespace\s+(.*?);', content)
        namespace = ns_match.group(1) if ns_match else "App\\Http\\Controllers"

        # Public methods
        methods = re.findall(r'public\s+function\s+(\w+)', content)
        # Exclude common constructor and magic methods
        methods = [m for m in methods if not m.startswith("__")]

        # Dependencies (constructor arguments)
        constructor_match = re.search(r'public\s+function\s+__construct\s*\((.*?)\)', content, re.DOTALL)
        dependencies = []
        if constructor_match:
            raw_args = constructor_match.group(1)
            # Find typed arguments like 'LedgerService $ledger' or 'Request $request'
            # Using regex to find words before variables
            args_list = re.findall(r'(\w+)\s+\$\w+', raw_args)
            dependencies = args_list

        # Services used (classes imported or instantiated)
        services_used = []
        # Find 'use App\Services\...\Class;'
        service_imports = re.findall(r'use\s+App\\Services\\(.*?);', content)
        for imp in service_imports:
            services_used.append(imp.split("\\")[-1])
        
        # Also look for service resolution in code e.g. app(SomeService::class)
        app_resolves = re.findall(r'app\(\s*(\w+)::class', content)
        for res in app_resolves:
            if res not in services_used:
                services_used.append(res)

        out.write(f"Controller: {namespace}\\{class_name}\n")
        out.write(f"  Path: {rel_path}\n")
        out.write(f"  Methods: {methods}\n")
        out.write(f"  Dependencies Injected: {dependencies}\n")
        out.write(f"  Services Used: {list(set(services_used))}\n")
        out.write("-" * 40 + "\n\n")

print(f"Summary written to {output_file}")
