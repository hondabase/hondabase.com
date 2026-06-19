import json

with open("scratch/untranslated.json") as f:
    untranslated = json.load(f)

subagents = []
batch_size = 10
num_subagents = 20

for i in range(num_subagents):
    start_idx = i * batch_size
    end_idx = min(start_idx + batch_size, len(untranslated))
    if start_idx >= len(untranslated):
        break
    
    batch = untranslated[start_idx:end_idx]
    prompt = "Translate the following articles from English to Portuguese (pt-PT):\n"
    for idx, item in enumerate(batch, 1):
        src = f"/var/www/hondabase/www/content/{item['rel_src']}"
        tgt = f"/var/www/hondabase/www/content/pt/{item['rel_src']}"
        prompt += f"{idx}. Source: {src}\n   Target: {tgt}\n"
    
    prompt += "\nRead the source files first, translate them carefully, write the translated content to the target paths, restore ownership to www-data, run php artisan app:lint-articles to make sure they are valid, and report back when finished."
    
    subagents.append({
        "TypeName": "article_translator",
        "Role": f"Article Translator Batch {i+1}",
        "Prompt": prompt,
        "Workspace": "inherit"
    })

with open("scratch/subagents_payload.json", "w") as f:
    json.dump({"Subagents": subagents}, f, indent=2)

print(f"Generated subagents payload for {len(subagents)} subagents, covering {min(len(untranslated), batch_size * num_subagents)} articles.")
