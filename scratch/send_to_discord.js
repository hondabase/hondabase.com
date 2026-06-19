import fs from 'fs';
import https from 'https';

const token = 'process.env.DISCORD_BOT_TOKEN';
const channelId = '1288629910092386374';

const payload = JSON.parse(fs.readFileSync('scratch/subagents_payload.json', 'utf8'));

function sendMessage(content) {
    return new Promise((resolve, reject) => {
        const data = JSON.stringify({ content });
        const options = {
            hostname: 'discord.com',
            port: 443,
            path: `/api/v10/channels/${channelId}/messages`,
            method: 'POST',
            headers: {
                'Authorization': `Bot ${token}`,
                'Content-Type': 'application/json',
                'Content-Length': Buffer.byteLength(data)
            }
        };

        const req = https.request(options, (res) => {
            let body = '';
            res.on('data', (d) => body += d);
            res.on('end', () => {
                if (res.statusCode >= 200 && res.statusCode < 300) {
                    resolve(body);
                } else {
                    reject(new Error(`Status: ${res.statusCode}, Body: ${body}`));
                }
            });
        });

        req.on('error', (e) => reject(e));
        req.write(data);
        req.end();
    });
}

async function run() {
    let currentMessage = '🚀 **Corrected Article Summaries (Batch Update)**\n\n';
    
    for (const entry of payload) {
        let block = `**${entry.slug}**\n`;
        if (entry.summaries.en) block += `EN: ${entry.summaries.en}\n`;
        if (entry.summaries.pt) block += `PT: ${entry.summaries.pt}\n`;
        block += '\n';

        if (currentMessage.length + block.length > 1900) {
            console.log('Sending message chunk...');
            await sendMessage(currentMessage);
            currentMessage = block;
            // Respect rate limits roughly
            await new Promise(r => setTimeout(r, 1000));
        } else {
            currentMessage += block;
        }
    }

    if (currentMessage) {
        console.log('Sending final message chunk...');
        await sendMessage(currentMessage);
    }
}

run().catch(console.error);
