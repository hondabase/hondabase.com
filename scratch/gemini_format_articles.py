import os
import subprocess
import time
import json
import argparse
import sys
import anthropic
import urllib.request
import urllib.error
import uuid

# Configuration
ANTHROPIC_KEY_FILE = '.anthropic_api_key'
DISCORD_TOKEN_FILE = '.discord_bot_token'
DISCORD_CHANNEL = '1288629910092386374'
RULES_PATH = 'docs/ARTICLE_FORMATTING_RULES.md'
MODEL = "claude-haiku-4-5-20251001" 

def get_anthropic_key():
    if os.path.exists(ANTHROPIC_KEY_FILE):
        return open(ANTHROPIC_KEY_FILE, 'r').read().strip()
    return os.environ.get("ANTHROPIC_API_KEY")

def get_discord_token():
    if os.path.exists(DISCORD_TOKEN_FILE):
        return open(DISCORD_TOKEN_FILE, 'r').read().strip()
    return None

def send_discord_message(content):
    token = get_discord_token()
    if not token: return
    data = json.dumps({"content": content}).encode('utf-8')
    req = urllib.request.Request(
        f"https://discord.com/api/v10/channels/{DISCORD_CHANNEL}/messages",
        data=data, headers={"Authorization": f"Bot {token}", "Content-Type": "application/json"}, method='POST'
    )
    try: urllib.request.urlopen(req)
    except: pass

def get_formatting_rules():
    if os.path.exists(RULES_PATH):
        return open(RULES_PATH, 'r', encoding='utf-8').read()
    return ""

def get_processed_articles():
    processed = set()
    try:
        res = subprocess.run(['git', 'log', '-i', '-E', '--grep=format|refactor|gemini|ai', '--name-only', '--format='], cwd='content', stdout=subprocess.PIPE, text=True)
        if res.returncode == 0:
            for l in res.stdout.splitlines():
                if l.strip().endswith('.md'): processed.add(os.path.join('content', l.strip()))
        res = subprocess.run(['git', 'status', '--porcelain'], cwd='content', stdout=subprocess.PIPE, text=True)
        if res.returncode == 0:
            for l in res.stdout.splitlines():
                p = l[3:].strip()
                if ' -> ' in p: p = p.split(' -> ')[1]
                if p.endswith('.md'): processed.add(os.path.join('content', p))
    except: pass
    return processed

def run_claude_batch(articles, rules, api_key):
    client = anthropic.Anthropic(api_key=api_key)
    
    print(f"Preparing Claude batch for {len(articles)} articles...")
    requests = []
    id_map = {}
    
    for i, path in enumerate(articles):
        with open(path, 'r', encoding='utf-8') as f:
            content = f.read()
        
        prompt = f"Format this Hondabase article according to these rules:\n{rules}\n\nArticle:\n{content}"
        
        # custom_id must be ^[a-zA-Z0-9_-]{1,64}$
        custom_id = f"art_{i}_{uuid.uuid4().hex[:8]}"
        id_map[custom_id] = path
        
        requests.append({
            "custom_id": custom_id,
            "params": {
                "model": MODEL,
                "max_tokens": 4096,
                "system": "You are an expert technical editor. Output ONLY the formatted markdown file content, including YAML frontmatter. Do not include any explanations or conversational text.",
                "messages": [{"role": "user", "content": prompt}]
            }
        })

    # Save id_map to a file in case of crash
    with open('scratch/claude_batch_id_map.json', 'w') as f:
        json.dump(id_map, f)

    print("Creating batch job...")
    try:
        batch = client.messages.batches.create(requests=requests)
        batch_id = batch.id
        print(f"Batch created: {batch_id}")
        send_discord_message(f"⏳ **Claude Batch Job Started**\nJob `{batch_id}` created for {len(articles)} articles using `{MODEL}`.")
    except Exception as e:
        print(f"Batch creation failed: {e}")
        send_discord_message(f"❌ **Claude Batch Creation Failed**\n```\n{e}\n```")
        return

    # Polling
    print(f"Polling status for batch: {batch_id}")
    while True:
        try:
            batch = client.messages.batches.retrieve(batch_id)
            print(f"Status: {batch.processing_status}")
            if batch.processing_status == "ended":
                break
        except Exception as e:
            print(f"Error polling: {e}")
        time.sleep(60)

    print("Batch finished processing.")
    
    # Process results
    processed_count = 0
    error_count = 0
    
    print("Downloading results...")
    try:
        for result in client.messages.batches.results(batch_id):
            custom_id = result.custom_id
            file_path = id_map.get(custom_id)
            
            if not file_path:
                print(f"Warning: No path mapping for custom_id {custom_id}")
                continue

            if result.result.type == "succeeded":
                try:
                    formatted_content = result.result.message.content[0].text
                    
                    if formatted_content.startswith("```markdown"): formatted_content = formatted_content[len("```markdown"):].lstrip()
                    if formatted_content.startswith("```"): formatted_content = formatted_content[len("```"):].lstrip()
                    if formatted_content.endswith("```"): formatted_content = formatted_content[:-len("```")].rstrip()
                    
                    with open(file_path, 'w', encoding='utf-8') as f:
                        f.write(formatted_content)
                    processed_count += 1
                except Exception as e:
                    print(f"Failed to process result for {file_path}: {e}")
                    error_count += 1
            else:
                print(f"Batch request failed for {file_path}: {result.result.error}")
                error_count += 1
                
        msg = f"✅ **Claude Article Formatting Complete**\nJob `{batch_id}` finished.\nProcessed: {processed_count}\nErrors: {error_count}"
        print(msg)
        send_discord_message(msg)
        
    except Exception as e:
        print(f"Result processing failed: {e}")
        send_discord_message(f"❌ **Claude Batch Result Processing Failed**\n```\n{e}\n```")

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--all", action="store_true")
    args = parser.parse_args()

    api_key = get_anthropic_key()
    if not api_key: 
        print("Anthropic API key missing.")
        sys.exit(1)

    rules = get_formatting_rules()
    
    processed_set = get_processed_articles()
    articles = []
    for root, dirs, files in os.walk('content'):
        dirs[:] = [d for d in dirs if not d.startswith('.')]
        if os.path.relpath(root, 'content').split(os.sep)[0] == 'docs': continue
        for f in files:
            if f.endswith('.md'):
                if root == 'content' and f.lower() in ['readme.md', 'contributing.md']: continue
                p = os.path.join(root, f)
                if p not in processed_set:
                    articles.append(p)

    print(f"Total articles to process: {len(articles)}")
    if not articles: 
        print("No articles to process.")
        return

    run_claude_batch(articles, rules, api_key)

if __name__ == "__main__":
    main()
