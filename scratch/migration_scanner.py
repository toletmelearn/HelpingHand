import os
import re

migrations_dir = r"c:\xampp\htdocs\HelpingHand\database\migrations"
output_file = r"c:\xampp\htdocs\HelpingHand\analyses\database_summary.txt"

if not os.path.exists(migrations_dir):
    print(f"Directory not found: {migrations_dir}")
    exit(1)

migration_files = sorted([f for f in os.listdir(migrations_dir) if f.endswith(".php")])

with open(output_file, "w", encoding="utf-8") as out:
    out.write(f"DATABASE MIGRATIONS SUMMARY\n")
    out.write(f"Total migration files: {len(migration_files)}\n")
    out.write("="*80 + "\n\n")

    tables = {}
    modified_columns = []

    for file_name in migration_files:
        file_path = os.path.join(migrations_dir, file_name)
        with open(file_path, "r", encoding="utf-8", errors="ignore") as f:
            content = f.read()

        # Find Schema::create
        create_matches = re.finditer(r'Schema::create\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*function', content)
        for m in create_matches:
            table_name = m.group(1)
            tables[table_name] = {
                'created_in': file_name,
                'columns': [],
                'indexes': [],
                'foreign_keys': []
            }
            # Find columns, indexes and foreign keys inside the schema block
            # For simplicity, search in the whole migration content
            columns_pattern = r'\$table->(\w+)\s*\(\s*[\'"]([^\'"]+)[\'"]'
            for col_match in re.finditer(columns_pattern, content):
                col_type = col_match.group(1)
                col_name = col_match.group(2)
                if col_type not in ['index', 'unique', 'foreign', 'foreignId']:
                    tables[table_name]['columns'].append(f"{col_name} ({col_type})")
            
            # Find unique/index columns
            index_pattern = r'\$table->(index|unique|primary)\s*\(\s*\[?(.*?)\]?\)'
            for idx_match in re.finditer(index_pattern, content):
                idx_type = idx_match.group(1)
                idx_cols = idx_match.group(2).replace("'", "").replace('"', "").strip()
                tables[table_name]['indexes'].append(f"{idx_type}: {idx_cols}")

            # Find foreignId / constrained
            foreign_pattern = r'\$table->(foreignId|foreign)\s*\(\s*[\'"]?([^\'"]+)[\'"]?\s*\)'
            for fk_match in re.finditer(foreign_pattern, content):
                fk_name = fk_match.group(2)
                tables[table_name]['foreign_keys'].append(fk_name)

        # Track table modifications (Schema::table)
        table_matches = re.finditer(r'Schema::table\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*function', content)
        for m in table_matches:
            table_name = m.group(1)
            modified_columns.append((table_name, file_name))

    out.write("--- CREATED TABLES ---\n")
    for t_name, data in sorted(tables.items()):
        out.write(f"Table: {t_name}\n")
        out.write(f"  Created in: {data['created_in']}\n")
        out.write(f"  Columns: {data['columns']}\n")
        if data['indexes']:
            out.write(f"  Indexes: {data['indexes']}\n")
        if data['foreign_keys']:
            out.write(f"  Foreign Keys: {data['foreign_keys']}\n")
        out.write("-" * 40 + "\n")

    out.write("\n\n--- TABLE MODIFICATIONS ---\n")
    for t_name, file in modified_columns:
        out.write(f"Table '{t_name}' modified in {file}\n")

print(f"Summary written to {output_file}")
