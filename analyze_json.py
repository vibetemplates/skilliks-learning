#!/usr/bin/env python3

import json
import sys
from collections import defaultdict

def analyze_structure(data, path=""):
    """Recursively analyze JSON structure"""
    structure = defaultdict(set)
    
    if isinstance(data, dict):
        for key, value in data.items():
            current_path = f"{path}.{key}" if path else key
            structure[current_path].add(type(value).__name__)
            
            if isinstance(value, (dict, list)):
                sub_structure = analyze_structure(value, current_path)
                structure.update(sub_structure)
                
    elif isinstance(data, list) and data:
        # For lists, analyze first item to understand structure
        structure[f"{path}[]"].add(type(data[0]).__name__)
        if isinstance(data[0], (dict, list)):
            sub_structure = analyze_structure(data[0], f"{path}[]")
            structure.update(sub_structure)
    
    return structure

def analyze_claude_json():
    print("=== CLAUDE.JSON STRUCTURE ===\n")
    
    with open('/var/www/claude.json', 'r') as f:
        data = json.load(f)
    
    # Analyze message types
    message_types = defaultdict(int)
    fields_by_type = defaultdict(set)
    
    for item in data:
        msg_type = item.get('type', 'unknown')
        message_types[msg_type] += 1
        
        # Collect all fields for this message type
        for key in item.keys():
            fields_by_type[msg_type].add(key)
    
    print("Message Types Count:")
    for msg_type, count in sorted(message_types.items()):
        print(f"  - {msg_type}: {count}")
    
    print("\nFields by Message Type:")
    for msg_type, fields in sorted(fields_by_type.items()):
        print(f"\n{msg_type.upper()} messages contain:")
        for field in sorted(fields):
            print(f"  - {field}")
            
            # For nested structures, show sub-fields
            if msg_type in ['assistant', 'user'] and field == 'message':
                # Sample the structure from first occurrence
                for item in data:
                    if item.get('type') == msg_type and 'message' in item:
                        msg = item['message']
                        if isinstance(msg, dict):
                            print("    Message sub-fields:")
                            for sub_field in sorted(msg.keys()):
                                print(f"      - {sub_field}")
                        break

def analyze_skilliks_json():
    print("\n\n=== SKILLIKS.JSON STRUCTURE ===\n")
    
    with open('/var/www/skilliks.json', 'r') as f:
        data = json.load(f)
    
    structure = analyze_structure(data)
    
    print("Complete Field Structure:")
    for path in sorted(structure.keys()):
        types = ', '.join(sorted(structure[path]))
        print(f"  {path}: {types}")

def main():
    try:
        analyze_claude_json()
        analyze_skilliks_json()
    except Exception as e:
        print(f"Error: {e}")
        import traceback
        traceback.print_exc()

if __name__ == "__main__":
    main()