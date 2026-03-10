#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

/**
 * Chrome Native Messaging uses a specific protocol:
 * - Input: 4-byte little-endian length prefix, then JSON
 * - Output: 4-byte little-endian length prefix, then JSON
 */
function readMessage() {
  return new Promise((resolve) => {
    let lengthBuffer = Buffer.alloc(0);

    const onData = (chunk) => {
      lengthBuffer = Buffer.concat([lengthBuffer, chunk]);

      if (lengthBuffer.length >= 4) {
        const messageLength = lengthBuffer.readUInt32LE(0);
        const remaining = lengthBuffer.slice(4);

        let messageBuffer = remaining;

        const readRest = (chunk) => {
          messageBuffer = Buffer.concat([messageBuffer, chunk]);
          if (messageBuffer.length >= messageLength) {
            process.stdin.removeListener('data', readRest);
            resolve(JSON.parse(messageBuffer.slice(0, messageLength).toString()));
          }
        };

        process.stdin.removeListener('data', onData);

        if (messageBuffer.length >= messageLength) {
          resolve(JSON.parse(messageBuffer.slice(0, messageLength).toString()));
        } else {
          process.stdin.on('data', readRest);
        }
      }
    };

    process.stdin.on('data', onData);
  });
}

function sendMessage(message) {
  const json = JSON.stringify(message);
  const buffer = Buffer.alloc(4 + json.length);
  buffer.writeUInt32LE(json.length, 0);
  buffer.write(json, 4);
  process.stdout.write(buffer);
}

async function main() {
  const message = await readMessage();

  const { image, filename, annotation, url, viewport, workspace } = message;

  if (!workspace || !image || !filename) {
    sendMessage({ success: false, error: 'Missing required fields: workspace, image, filename' });
    process.exit(1);
  }

  const feedbackDir = path.join(workspace, 'storage', 'app', 'agent-captures', 'feedback');

  // Ensure directory exists
  fs.mkdirSync(feedbackDir, { recursive: true });

  // Write PNG file
  const imagePath = path.join(feedbackDir, filename);
  fs.writeFileSync(imagePath, Buffer.from(image, 'base64'));

  // Write JSON manifest
  const manifest = {
    image: filename,
    url: url || null,
    annotation: annotation || null,
    timestamp: new Date().toISOString(),
    viewport: viewport || null,
  };
  fs.writeFileSync(`${imagePath}.json`, JSON.stringify(manifest, null, 2));

  sendMessage({ success: true, path: imagePath });
}

main().catch((err) => {
  sendMessage({ success: false, error: err.message });
  process.exit(1);
});
