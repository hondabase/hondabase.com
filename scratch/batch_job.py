import os
import json
import time
from google import genai
from google.genai import types

def run_batch():
    # Only process the remaining articles
    articles = []
    processed = set()
    
    # Read processed from git
    import subprocess
    result = subprocess.run(
        ['git', 'log', '-i', '-E', '--grep=format|refactor|gemini|ai', '--name-only', '--format='],
        cwd='content', stdout=subprocess.PIPE, text=True
    )
    for line in result.stdout.splitlines():
        if line.strip().endswith('.md'):
            processed.add(os.path.join('content', line.strip()))
            
    result = subprocess.run(['git', 'status', '--porcelain'], cwd='content', stdout=subprocess.PIPE, text=True)
    for line in result.stdout.splitlines():
        path = line[3:].strip()
        if ' -> ' in path: path = path.split(' -> ')[1]
        if path.endswith('.md'):
            processed.add(os.path.join('content', path))

    for root, dirs, files in os.walk('content'):
        dirs[:] = [d for d in dirs if not d.startswith('.')]
        if os.path.relpath(root, 'content').split(os.sep)[0] == 'docs': continue
        for f in files:
            if f.endswith('.md'):
                if root == 'content' and f.lower() in ['readme.md', 'contributing.md']: continue
                p = os.path.join(root, f)
                if p not in processed:
                    articles.append(p)

    if not articles:
        print("No articles left.")
        return

    print(f"Preparing batch for {len(articles)} articles...")
    
    rules = open('docs/ARTICLE_FORMATTING_RULES.md').read()
    key = open('.gemini_api_key').read().strip()
    client = genai.Client(api_key=key)

    requests = []
    for path in articles:
        with open(path, 'r', encoding='utf-8') as f:
            content = f.read()
        prompt = f"Format this Hondabase article according to these rules:\n{rules}\n\nArticle:\n{content}"
        
        req = types.InlinedRequest(
            contents=prompt,
            metadata={"file_path": path} # Attach the local path to map it back later
        )
        requests.append(req)

    print("Submitting batch job...")
    try:
        # Trying a model that is confirmed to support batch in the list we pulled earlier
        job = client.batches.create(
            model="gemini-2.0-flash",
            src=requests
        )
        print(f"Job created successfully: {job.name}")
        
        while job.state in ['JOB_STATE_PENDING', 'JOB_STATE_RUNNING', 'PENDING', 'RUNNING', 'INITIALIZING']:
            print(f"Status: {job.state}")
            time.sleep(30)
            job = client.batches.get(name=job.name)
            
        print(f"Job finished: {job.state}")
        
        # Process results
        if job.state in ['JOB_STATE_SUCCEEDED', 'SUCCEEDED']:
            print("Processing output...")
            
            # The output URI
            output_uri = job.output_uri
            print(f"Output available at: {output_uri}")
            
    except Exception as e:
        print("Batch API Error:", e)

if __name__ == "__main__":
    run_batch()
