#!/usr/bin/env python3
import subprocess
import sys
import os

# Change to script directory
script_dir = os.path.dirname(os.path.abspath(__file__))
os.chdir(script_dir)

# Run the create_zip.py script
result = subprocess.run([sys.executable, 'create_zip.py'], capture_output=True, text=True)

print(result.stdout)
if result.stderr:
    print(result.stderr, file=sys.stderr)

sys.exit(result.returncode)

