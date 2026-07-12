import fs from 'node:fs';

const version = '2.2.5';
const main = fs.readFileSync('image-usage-audit.php', 'utf8');
const readme = fs.readFileSync('readme.txt', 'utf8');
const pot = fs.readFileSync('languages/image-usage-audit.pot', 'utf8');
const license = fs.readFileSync('LICENSE', 'utf8');

const checks = [
	[main.includes(`Version: ${version}`), 'plugin header version'],
	[main.includes(`define( 'IUA_VERSION', '${version}' )`), 'IUA_VERSION'],
	[readme.includes(`Stable tag: ${version}`), 'readme stable tag'],
	[pot.includes(`Project-Id-Version: Image Usage Audit ${version}`), 'POT project version'],
	[main.includes('Text Domain: image-usage-audit'), 'plugin text domain'],
	[pot.includes('Language-Team:'), 'POT metadata'],
	[license.includes('GNU GENERAL PUBLIC LICENSE'), 'GPL heading'],
	[license.includes('Version 2, June 1991'), 'GPL version'],
	[license.includes('END OF TERMS AND CONDITIONS'), 'GPL terms'],
];

for (const [passed, label] of checks) {
	if (!passed) {
		throw new Error(`Metadata validation failed: ${label}`);
	}
}

const tagsLine = readme.match(/^Tags:\s*(.+)$/m);
const tags = tagsLine ? tagsLine[1].split(',').map((tag) => tag.trim()).filter(Boolean) : [];

if (tags.length < 1 || tags.length > 5) {
	throw new Error(`readme.txt must contain 1-5 tags; found ${tags.length}.`);
}

const readmeSections = readme.split(/\r?\n\r?\n/);
const shortDescription = (readmeSections[1] || '').split(/\r?\n/, 1)[0].trim();

if (!shortDescription || shortDescription.length > 150) {
	throw new Error(`readme.txt short description must be 1-150 characters; found ${shortDescription.length}.`);
}

if (/^== Screenshots ==$/m.test(readme)) {
	throw new Error('readme.txt lists screenshots, but no WordPress.org screenshot assets are distributed.');
}

console.log(JSON.stringify({ result: 'pass', version, tags: tags.length, shortDescription: shortDescription.length }));
