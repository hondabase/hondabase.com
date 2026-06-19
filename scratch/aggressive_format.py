import os
import re

def aggressive_format(content):
    parts = content.split('---', 2)
    if len(parts) < 3:
        return content
    
    frontmatter = parts[1]
    body = parts[2]
    
    # Ensure Header has double newline after it
    body = re.sub(r'(#+ .*)(\n)([^\n#])', r'\1\n\n\3', body)
    # Break before Headers if they are mashed into a line
    body = re.sub(r'([^\n])\s*(#+ )', r'\1\n\n\2', body)
    
    # Labels that should be on their own line or start a paragraph
    block_labels = [
        'Compare to', 'Comparar com',
        'Note', 'Nota', 'Notes', 'Notas',
        'EDIT', 'See', 'Ver'
    ]
    
    # Labels that are part of a metadata block (Author/Date)
    meta_labels = [
        'Author', 'Autor',
        'Date', 'Data'
    ]
    
    for label in block_labels:
        # Match "Label:" or "Label :"
        pattern = rf'([^\n]) ({label}\s*:)'
        body = re.sub(pattern, rf'\1\n\n**\2**', body)
        pattern = rf'\n({label}\s*:)'
        body = re.sub(pattern, rf'\n\n**\1**', body)

    for label in meta_labels:
        # Match "Label: content" and turn into "**Label:** content" with a newline before if mashed
        pattern = rf'([^\n]) ({label}\s*:)'
        body = re.sub(pattern, rf'\1\n\n**\2**', body)
        pattern = rf'\n({label}\s*:)'
        body = re.sub(pattern, rf'\n\n**\1**', body)

    # Special case: split content AFTER meta labels
    body = re.sub(r'(\*\*(?:Date|Data)\s*:\*\*\s*[0-9\-/ :]+) ([A-Z])', r'\1\n\n\2', body)
    body = re.sub(r'(\*\*(?:Author|Autor)\s*:\*\*\s*[a-zA-Z0-9_-]+) ([A-Z])', r'\1\n\n\2', body)

    # 4. Special case for mashed bullet points
    body = re.sub(r'([a-zA-Z0-9.])(\* )', r'\1\n\n\2', body)
    body = re.sub(r'(\* [^*]+)(\* )', r'\1\n\2', body)
    
    # 5. Break before "EOBD is" or "EOBD é"
    body = re.sub(r'([^\n]) (EOBD [is|é])', r'\1\n\n### \2', body)

    # 7. Clean up triple+ newlines
    body = re.sub(r'\n{3,}', '\n\n', body)
    
    body = body.strip()
    
    # Ensure newline between frontmatter and body
    return f"---{frontmatter}---\n\n{body}\n"

def process_all_articles(article_list):
    with open(article_list, 'r') as f:
        paths = [line.strip() for line in f if line.strip()]
    
    for path in paths:
        if os.path.exists(path):
            with open(path, 'r') as f:
                original = f.read()
            
            fixed = aggressive_format(original)
            if fixed != original:
                with open(path, 'w') as f:
                    f.write(fixed)
                print(f"Refined Aggressive Format: {path}")

if __name__ == "__main__":
    import sys
    if len(sys.argv) > 1:
        process_all_articles(sys.argv[1])
    else:
        if os.path.exists('all_articles.txt'):
            process_all_articles('all_articles.txt')
