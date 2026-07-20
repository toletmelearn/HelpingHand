import os
import re

sidebar_path = r"c:\xampp\htdocs\HelpingHand\resources\views\layouts\sidebar.blade.php"
output_file = r"c:\xampp\htdocs\HelpingHand\analyses\sidebar_summary.txt"

if not os.path.exists(sidebar_path):
    print(f"File not found: {sidebar_path}")
    exit(1)

with open(sidebar_path, "r", encoding="utf-8", errors="ignore") as f:
    content = f.read()

# Let's write the analysis to a summary file
with open(output_file, "w", encoding="utf-8") as out:
    out.write("SIDEBAR AUDIT REPORT\n")
    out.write("="*80 + "\n\n")

    # 1. Look for registry-driven dynamic menu loader
    # Dynamic loader usually loops through $erpRegistry->getSidebarEntries()
    registry_driven = "getSidebarEntries()" in content
    out.write(f"Registry-driven dynamic loading detected: {'Yes' if registry_driven else 'No'}\n\n")

    # 2. Extract hardcoded sidebar sections
    # Sidebar sections usually have headers or specific class attributes
    # Let's search for all href links in sidebar
    # Format: href="{{ route('...') }}" or href="..."
    links = re.findall(r'<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>', content, re.DOTALL)
    
    out.write(f"Total links found: {len(links)}\n")
    out.write("="*40 + "\n")
    
    placeholders = []
    hardcoded = []
    
    # We want to identify the title and URL of each entry, and check for placeholders or routes
    for url, inner_html in links:
        # Strip html tags from inner_html to get the title
        title = re.sub(r'<[^>]+>', '', inner_html).strip()
        title = " ".join(title.split())
        
        # Check if URL is placeholder
        is_placeholder = url == "#" or "javascript:void" in url or url == ""
        
        # Check if it uses a laravel route helper
        route_name = ""
        route_match = re.search(r'route\s*\(\s*[\'"]([^\'"]+)[\'"]', url)
        if route_match:
            route_name = route_match.group(1)

        # Check permissions wrapping (if any)
        # Search backward from the link match to find surrounding @can or role check
        # This is a bit complex in regex, so we'll just check if there is a @can or role string nearby
        
        if is_placeholder:
            placeholders.append((title, url))
        else:
            hardcoded.append((title, url, route_name))

    out.write("\n--- HARDCODED/STATIC ENTRIES ---\n")
    for title, url, route_name in hardcoded:
        route_str = f" [Route: {route_name}]" if route_name else ""
        out.write(f"• Title: '{title}' | URL: '{url}'{route_str}\n")

    out.write("\n--- PLACEHOLDER ENTRIES (Points to '#' or JS voids) ---\n")
    for title, url in placeholders:
        out.write(f"• Title: '{title}' | URL: '{url}'\n")

    # Let's scan for permission directives
    can_blocks = re.findall(r'@can\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)', content)
    out.write(f"\n--- PERMISSION GATES FOUND IN SIDEBAR ---\n")
    for can in sorted(list(set(can_blocks))):
        out.write(f"• {can}\n")

    # Let's scan for role check directives
    role_blocks = re.findall(r'@if\s*\(\s*auth\(\s*\)->user\(\s*\)->hasRole\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)\s*\)', content)
    # Also support role middleware or hasAnyRole or isSuperAdmin
    other_checks = re.findall(r'hasRole\s*\((.*?)\)', content)
    out.write(f"\n--- ROLE CHECKS FOUND IN SIDEBAR ---\n")
    for role in sorted(list(set(role_blocks + other_checks))):
        out.write(f"• {role}\n")

print(f"Summary written to {output_file}")
