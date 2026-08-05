import fs from 'node:fs';

const tag = process.argv[2];
const main = fs.readFileSync('pixcensus-media-audit.php', 'utf8');
const version = main.match(/^\s*\*\s*Version:\s*(\d+\.\d+\.\d+)\s*$/m)?.[1];

if (!version) {
	throw new Error('Unable to read the semantic plugin version from the PHP header.');
}

if (tag !== `v${version}`) {
	throw new Error(`Release tag ${tag || '<missing>'} does not match plugin version v${version}.`);
}

console.log(JSON.stringify({ result: 'pass', tag, version }));
