import os

content_dir = 'content'
results = []

for root, dirs, files in os.walk(content_dir):
    if '.git' in root:
        continue
    for file in files:
        if file.endswith('.md'):
            path = os.path.join(root, file)
            with open(path, 'r') as f:
                content = f.read()
            
            parts = content.split('---', 2)
            if len(parts) >= 3:
                body = parts[2].strip()
            else:
                body = content.strip()
            
            # Identify "wall of text" if body is long but has few paragraph breaks
            if len(body) > 500:
                para_count = body.count('\n\n')
                if para_count < 2:
                    results.append(path)

for path in results:
    print(path)
