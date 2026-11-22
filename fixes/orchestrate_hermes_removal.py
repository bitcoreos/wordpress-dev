import subprocess, sys, os

REMOTE_HOST = "root@64.23.153.113"
SSH_KEY = "/root/.ssh/hyperion_wp"
SSH_OPTS = ["-o", "StrictHostKeyChecking=no", "-o", "ConnectTimeout=10"]
BASE = "/var/lib/docker/volumes/wordpress_wordpress_data/_data/wp-content/plugins/bitcore-core"

def ssh_cmd(cmd):
    full = ["ssh", "-i", SSH_KEY] + SSH_OPTS + [REMOTE_HOST, cmd]
    result = subprocess.run(full, capture_output=True, text=True)
    return result.returncode, result.stdout, result.stderr

def ssh_write_file(remote_path, content):
    """Write file via stdin using cat >"""
    full = ["ssh", "-i", SSH_KEY] + SSH_OPTS + [REMOTE_HOST, f"cat > {remote_path}"]
    result = subprocess.run(full, input=content, capture_output=True, text=True)
    return result.returncode, result.stdout, result.stderr

# Step 1: Write the removal script to remote server
print("Writing removal script to ASHI...")
with open("/a0/usr/projects/hyperion/remove_hermes.py", "r") as f:
    script_content = f.read()

code, out, err = ssh_write_file("/tmp/remove_hermes.py", script_content)
if code != 0:
    print(f"Failed to write script: {err}")
    sys.exit(1)
print("Script written OK")

# Step 2: Execute removal script
print("\nExecuting removal script...")
code, out, err = ssh_cmd("python3 /tmp/remove_hermes.py")
print(out)
if err:
    print("STDERR:", err)
if code != 0:
    print(f"Script failed with code {code}")
    sys.exit(1)

# Step 3: Database cleanup
print("\nDatabase cleanup...")
options = [
    "bitcore_hermes_url",
    "bitcore_hermes_api_key",
    "bitcore_callback_secret",
    "bitcore_enable_chat",
]
for opt in options:
    code, out, err = ssh_cmd(f"docker exec wordpress-app wp option delete {opt} --allow-root 2>&1 || echo 'OPTION_NOT_FOUND_OR_ERROR'")
    print(f"  delete {opt}: {out.strip()}")

# Step 4: Verification
print("\nVerification...")

# 4a: Check deleted files
for f in ["classes/services/connector-hermes.php", "rest/hermes-api.php"]:
    code, out, err = ssh_cmd(f"test -f {BASE}/{f} && echo EXISTS || echo GONE")
    print(f"  {f}: {out.strip()}")

# 4b: grep for hermes
code, out, err = ssh_cmd(f"grep -ri 'hermes' {BASE} --include='*.php' | wc -l")
hermes_count = out.strip()
print(f"  hermes references remaining: {hermes_count}")

# 4c: PHP syntax check on modified files
files_to_check = [
    "classes/core.php",
    "classes/container.php",
    "admin/dashboard.php",
    "admin/agents-models.php",
    "admin/api-tools.php",
    "admin/settings.php",
    "admin/mcp.php",
]
for f in files_to_check:
    code, out, err = ssh_cmd(f"docker exec wordpress-app php -l {BASE}/{f} 2>&1")
    status = "OK" if code == 0 else f"FAIL({code})"
    print(f"  syntax {f}: {status}")

# 4d: REST API health check
code, out, err = ssh_cmd("docker exec wordpress-app wp eval 'echo rest_url(\"bitcore/v1/health\");' --allow-root 2>&1 | head -1")
print(f"  REST health endpoint: {out.strip()}")

print("\n=== REMOVAL COMPLETE ===")
if hermes_count == "0":
    print("SUCCESS: Zero hermes references found.")
else:
    print(f"WARNING: {hermes_count} hermes references still remain. Manual review needed.")
