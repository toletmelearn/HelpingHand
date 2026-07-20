import os
import re

models_dir = r"c:\xampp\htdocs\HelpingHand\app\Models"
output_file = r"c:\xampp\htdocs\HelpingHand\analyses\model_summary.txt"

if not os.path.exists(models_dir):
    print(f"Directory not found: {models_dir}")
    exit(1)

model_files = [f for f in os.listdir(models_dir) if f.endswith(".php")]

with open(output_file, "w", encoding="utf-8") as out:
    out.write(f"MODEL SCANNER SUMMARY\n")
    out.write(f"Total model files found: {len(model_files)}\n")
    out.write("="*80 + "\n\n")

    for file_name in sorted(model_files):
        file_path = os.path.join(models_dir, file_name)
        with open(file_path, "r", encoding="utf-8", errors="ignore") as f:
            content = f.read()

        class_name = file_name.replace(".php", "")
        
        # Extract table
        table_match = re.search(r'protected\s+\$table\s*=\s*[\'"]([^\'"]+)[\'"]', content)
        table = table_match.group(1) if table_match else "Inferred (plural of class)"

        # Extract fillable
        fillable_match = re.search(r'protected\s+\$fillable\s*=\s*\[(.*?)\];', content, re.DOTALL)
        fillable = []
        if fillable_match:
            raw_fillable = fillable_match.group(1)
            fillable = [x.strip().replace("'", "").replace('"', "") for x in re.split(r',\s*', raw_fillable) if x.strip()]

        # Extract casts
        casts_match = re.search(r'protected\s+\$casts\s*=\s*\[(.*?)\];', content, re.DOTALL)
        casts = {}
        if casts_match:
            raw_casts = casts_match.group(1)
            for cast_line in re.split(r',\s*', raw_casts):
                if "=>" in cast_line:
                    k, v = cast_line.split("=>")
                    casts[k.strip().replace("'", "").replace('"', "")] = v.strip().replace("'", "").replace('"', "")

        # Check soft deletes
        has_soft_deletes = "use SoftDeletes" in content or "use Illuminate\\Database\\Eloquent\\SoftDeletes" in content

        # Extract relationships (methods with return $this->hasMany, belongsTo, etc.)
        rel_pattern = r'public\s+function\s+(\w+)\s*\([^\)]*\)\s*\{(.*?)\}'
        rel_matches = re.finditer(rel_pattern, content, re.DOTALL)
        relationships = []
        for match in rel_matches:
            method_name = match.group(1)
            method_body = match.group(2)
            if "belongsTo" in method_body:
                relationships.append(f"{method_name} (belongsTo)")
            elif "hasMany" in method_body:
                relationships.append(f"{method_name} (hasMany)")
            elif "belongsToMany" in method_body:
                relationships.append(f"{method_name} (belongsToMany)")
            elif "hasOne" in method_body:
                relationships.append(f"{method_name} (hasOne)")
            elif "morphTo" in method_body:
                relationships.append(f"{method_name} (morphTo)")
            elif "morphMany" in method_body:
                relationships.append(f"{method_name} (morphMany)")

        # Custom accessors & mutators (methods starting with get...Attribute or set...Attribute, or Attribute::make)
        accessors_mutators = []
        attr_matches = re.findall(r'public\s+function\s+get(\w+)Attribute', content)
        for attr in attr_matches:
            accessors_mutators.append(f"get{attr}Attribute (accessor)")
        mut_matches = re.findall(r'public\s+function\s+set(\w+)Attribute', content)
        for mut in mut_matches:
            accessors_mutators.append(f"set{mut}Attribute (mutator)")
        
        # Support for new Attribute type return Attribute::make
        new_attr_matches = re.findall(r'public\s+function\s+(\w+)\s*\([^\)]*\)\s*:\s*Attribute', content)
        for attr in new_attr_matches:
            accessors_mutators.append(f"{attr} (Attribute)")

        # Global scopes
        global_scopes = []
        if "booted" in content or "boot" in content:
            scope_matches = re.findall(r'static::addGlobalScope\((.*?)\)', content)
            for sc in scope_matches:
                global_scopes.append(sc.strip())

        # Business responsibility based on comments or class name
        docblock_match = re.search(r'/\*\*(.*?)\*/\s*class', content, re.DOTALL)
        docblock = docblock_match.group(1).strip() if docblock_match else ""
        docblock_clean = " ".join([line.strip().lstrip('*').strip() for line in docblock.split("\n") if line.strip()])
        responsibility = docblock_clean if docblock_clean else f"Manages {class_name} records."

        out.write(f"Class: {class_name}\n")
        out.write(f"  Table: {table}\n")
        out.write(f"  Fillable: {fillable}\n")
        out.write(f"  Casts: {casts}\n")
        out.write(f"  Soft Deletes: {'Yes' if has_soft_deletes else 'No'}\n")
        out.write(f"  Relationships: {relationships}\n")
        out.write(f"  Accessors/Mutators: {accessors_mutators}\n")
        out.write(f"  Global Scopes: {global_scopes}\n")
        out.write(f"  Responsibility: {responsibility}\n")
        out.write("-" * 40 + "\n\n")

print(f"Summary written to {output_file}")
