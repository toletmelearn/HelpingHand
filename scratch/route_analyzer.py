import json
import os

routes_path = r"c:\xampp\htdocs\HelpingHand\analyses\routes-dump\full_routes.json"
output_path = r"c:\xampp\htdocs\HelpingHand\analyses\routes-dump\route_summary.txt"

if not os.path.exists(routes_path):
    print(f"File not found: {routes_path}")
    exit(1)

content = ""
# Try UTF-16 first (PowerShell redirect default)
try:
    with open(routes_path, "r", encoding="utf-16") as f:
        content = f.read().replace('\x00', '')
except Exception as e:
    print(f"Failed to read as UTF-16: {e}")
    # Try UTF-8 as fallback
    try:
        with open(routes_path, "r", encoding="utf-8") as f:
            content = f.read().replace('\x00', '')
    except Exception as e2:
        print(f"Failed to read as UTF-8: {e2}")
        exit(1)

routes = json.loads(content)

with open(output_path, "w", encoding="utf-8") as out:
    def write_line(msg):
        out.write(msg + "\n")
        # Keep print minimal to avoid truncation in output view
        # print(msg)

    write_line(f"Total routes loaded: {len(routes)}")

    uris = {}
    names = {}
    controllers = {}
    duplicate_uris = []
    duplicate_names = []
    test_routes = []

    for r in routes:
        uri = r.get("uri")
        name = r.get("name")
        action = r.get("action")
        methods = r.get("method")
        
        uri_key = f"{methods} {uri}"
        uris[uri_key] = uris.get(uri_key, 0) + 1
        if uris[uri_key] > 1:
            duplicate_uris.append((uri_key, action))
            
        if name:
            names[name] = names.get(name, 0) + 1
            if names[name] > 1:
                duplicate_names.append((name, uri_key, action))
                
        controllers[action] = controllers.get(action, 0) + 1

        if "test" in uri.lower() or "test" in str(name).lower() or "debug" in uri.lower() or "_test" in uri.lower():
            test_routes.append({"uri": uri, "name": name, "action": action, "method": methods})

    write_line(f"\nUnique Route Names: {len(names)}")
    write_line(f"Duplicate Names count: {len(duplicate_names)}")
    
    # Group duplicate names to see their collisions
    write_line("\nName Collisions detail (same name, multiple routes):")
    collisions_found = False
    for name_str, count in names.items():
        if count > 1:
            collisions_found = True
            write_line(f"Route Name: '{name_str}' occurs {count} times:")
            for r in routes:
                if r.get("name") == name_str:
                    write_line(f"  - URI: {r.get('method')} {r.get('uri')} | Action: {r.get('action')}")

    if not collisions_found:
        write_line("No route name collisions detected.")

    write_line(f"\nDuplicate URIs count: {len(duplicate_uris)}")
    for dup in duplicate_uris:
        write_line(f"  - URI: {dup[0]} | Action: {dup[1]}")

    write_line(f"\nTest/Debug Routes count: {len(test_routes)}")
    for tr in test_routes:
        write_line(f"  - {tr['method']} {tr['uri']} | Name: {tr['name']} | Action: {tr['action']}")

    # Let's check for controllers that are referenced by multiple routes (potential duplicate routing)
    write_line("\nControllers referenced by multiple actions/routes:")
    for action, count in sorted(controllers.items(), key=lambda x: x[1], reverse=True):
        if count > 1 and "Closure" not in action:
            write_line(f"  - {action}: {count} references")

    prefixes = {}
    for r in routes:
        uri = r.get("uri")
        parts = uri.split("/")
        prefix = parts[0] if parts else ""
        if len(parts) > 1 and parts[0] in ["admin", "api", "teacher", "parent"]:
            prefix = f"{parts[0]}/{parts[1]}"
        prefixes[prefix] = prefixes.get(prefix, 0) + 1

    write_line("\nRoute distribution by prefix:")
    for pref, count in sorted(prefixes.items(), key=lambda x: x[1], reverse=True):
        write_line(f"  - {pref}: {count}")

print(f"Summary successfully written to {output_path}")
