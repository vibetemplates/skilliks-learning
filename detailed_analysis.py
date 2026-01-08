#!/usr/bin/env python3

import json
from collections import defaultdict

def analyze_claude_detailed():
    print("=== CLAUDE.JSON DETAILED FIELD ANALYSIS ===\n")
    
    with open('/var/www/claude.json', 'r') as f:
        data = json.load(f)
    
    # Analyze content types within messages
    content_types = set()
    tool_names = set()
    
    for item in data:
        if item.get('type') == 'assistant' and 'message' in item:
            msg = item['message']
            if 'content' in msg and isinstance(msg['content'], list):
                for content in msg['content']:
                    if isinstance(content, dict):
                        content_types.add(content.get('type'))
                        if content.get('type') == 'tool_use':
                            tool_names.add(content.get('name'))
        
        elif item.get('type') == 'user' and 'message' in item:
            msg = item['message']
            if 'content' in msg and isinstance(msg['content'], list):
                for content in msg['content']:
                    if isinstance(content, dict):
                        content_types.add(content.get('type'))
    
    print("Content Types Found:")
    for ct in sorted(content_types):
        print(f"  - {ct}")
    
    print("\nTool Names Used:")
    for tool in sorted(tool_names):
        print(f"  - {tool}")
    
    # Sample detailed structures
    print("\n=== SAMPLE MESSAGE STRUCTURES ===")
    
    # System message
    for item in data:
        if item.get('type') == 'system':
            print("\nSYSTEM Message Structure:")
            print(json.dumps(item, indent=2))
            break
    
    # Assistant message with text
    for item in data:
        if item.get('type') == 'assistant' and 'message' in item:
            msg = item['message']
            if 'content' in msg and isinstance(msg['content'], list):
                for content in msg['content']:
                    if content.get('type') == 'text':
                        print("\nASSISTANT Text Message Structure:")
                        print(json.dumps(item, indent=2)[:500] + "...")
                        break
                break
    
    # User tool result
    for item in data:
        if item.get('type') == 'user' and 'message' in item:
            msg = item['message']
            if 'content' in msg and isinstance(msg['content'], list):
                for content in msg['content']:
                    if content.get('type') == 'tool_result':
                        print("\nUSER Tool Result Structure:")
                        print(json.dumps(item, indent=2)[:500] + "...")
                        break
                break

def analyze_skilliks_detailed():
    print("\n\n=== SKILLIKS.JSON DETAILED ANALYSIS ===\n")
    
    with open('/var/www/skilliks.json', 'r') as f:
        data = json.load(f)
    
    # Show tool execution details
    if 'data' in data and 'toolsExecuted' in data['data']:
        print("Tools Executed Structure:")
        for i, tool in enumerate(data['data']['toolsExecuted']):
            print(f"\nTool {i+1}:")
            print(f"  - tool: {tool.get('tool')}")
            print(f"  - parameters: {json.dumps(tool.get('parameters', {}), indent=4)}")
            if 'result' in tool:
                result_str = str(tool['result'])
                if len(result_str) > 100:
                    print(f"  - result: {result_str[:100]}...")
                else:
                    print(f"  - result: {result_str}")

analyze_claude_detailed()
analyze_skilliks_detailed()