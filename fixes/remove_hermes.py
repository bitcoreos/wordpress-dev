import shutil, os, re, sys

BASE = "/var/lib/docker/volumes/wordpress_wordpress_data/_data/wp-content/plugins/bitcore-core"

# 1. DELETE files
for f in ["classes/services/connector-hermes.php", "rest/hermes-api.php"]:
    path = os.path.join(BASE, f)
    if os.path.exists(path):
        shutil.copy2(path, path + ".bak")
        os.remove(path)
        print(f"DELETED: {f}")
    else:
        print(f"MISSING (already gone?): {f}")

# Helper to patch files
def patch_file(rel_path, removals, multiline_removals=None):
    path = os.path.join(BASE, rel_path)
    if not os.path.exists(path):
        print(f"MISSING: {rel_path}")
        return
    shutil.copy2(path, path + ".bak")
    with open(path, "r") as f:
        lines = f.readlines()

    # Single-line removals
    new_lines = []
    for line in lines:
        keep = True
        for r in removals:
            if r in line:
                keep = False
                break
        if keep:
            new_lines.append(line)

    # Multi-line removals
    if multiline_removals:
        final_lines = []
        i = 0
        while i < len(new_lines):
            skipped = False
            for start, end in multiline_removals:
                if start in new_lines[i]:
                    j = i
                    while j < len(new_lines) and end not in new_lines[j]:
                        j += 1
                    if j < len(new_lines):
                        i = j + 1
                        skipped = True
                        break
            if not skipped:
                final_lines.append(new_lines[i])
                i += 1
        new_lines = final_lines

    with open(path, "w") as f:
        f.writelines(new_lines)
    print(f"PATCHED: {rel_path}")

# 2. core.php
patch_file("classes/core.php", [
    "public ?BITCORE_Rest_Hermes_Api $rest_hermes = null;",
    "require_once BITCORE_PATH . 'classes/services/connector-hermes.php';",
    "$this->rest_hermes = new BITCORE_Rest_Hermes_Api($this);",
    "$this->connector_hermes = new BITCORE_Services_Connector_Hermes($this);",
    "// REST Hermes initialized in init_rest() for early hook timing",
])

# 3. container.php
patch_file("classes/container.php", [
    "// Hermes Connector",
    "$this->set('connector_hermes'",
    "new BITCORE_Services_Connector_Hermes($c->get('core'))",
])

# 4. dashboard.php
patch_file("admin/dashboard.php", [], [
    ("$hermes_health = wp_remote_get(get_option('bitcore_hermes_url'", "$hermes_status = is_wp_error"),
    ("<tr><td>Hermes Agent (Local)", "</tr>\n"),
])

# 5. agents-models.php
patch_file("admin/agents-models.php", [
    "$hermes_url = get_option('bitcore_hermes_url'",
    "$hermes_health = wp_remote_get($hermes_url",
    "$hermes_data = !is_wp_error",
], [
    ("<h3 style=\"margin: 0;\">Hermes</h3>", "</table>\n            </div>\n            <div class=\"agent-card\""),
])

# 6. api-tools.php
patch_file("admin/api-tools.php", [
    "$hermes_url = get_option('bitcore_hermes_url'",
    "$hermes_skills = [];",
    "$skills_response = wp_remote_get($hermes_url",
    "if (!is_wp_error($skills_response)) {",
    "    $skills_data = json_decode(wp_remote_retrieve_body($skills_response), true);",
    "    if (isset($skills_data['skills'])) {",
    "        $hermes_skills = $skills_data['skills'];",
    "    }",
    "}",
    '<a href="#hermes" class="tab">Hermes Skills</a>',
], [
    ('<div id="hermes" class="tab-content">', "</div>\n    \n    <div id=\"testing\""),
])

# 7. settings.php
patch_file("admin/settings.php", [
    "    'bitcore_hermes_url', 'bitcore_callback_secret',",
    "$hermes_url = get_option('bitcore_hermes_url'",
    "<th>Internal URL</th>",
    '<input type="url" name="bitcore_hermes_url"',
    "Default: <code>http://172.17.0.1:8787</code>",
    "<th>Callback Secret</th>",
    '<input type="text" name="bitcore_callback_secret"',
    '<td><label><input type="checkbox" name="bitcore_enable_chat" value="1"',
    "Enable /hermes/chat endpoint",
])

# 8. mcp.php
patch_file("admin/mcp.php", [
    "e.path.includes('hermes') || ",
])

print("\n=== DONE ===")
