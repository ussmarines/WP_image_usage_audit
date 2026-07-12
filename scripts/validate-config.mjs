import fs from 'node:fs';
import path from 'node:path';
import { parse as parseYaml } from 'yaml';

const ignoredDirectories = new Set(['.git', 'dist', 'node_modules', 'vendor']);
const jsonFiles = [];

function collectJson(directory) {
	for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
		if (entry.isDirectory() && ignoredDirectories.has(entry.name)) {
			continue;
		}

		const entryPath = path.join(directory, entry.name);

		if (entry.isDirectory()) {
			collectJson(entryPath);
		} else if (entry.isFile() && entry.name.endsWith('.json')) {
			jsonFiles.push(entryPath);
		}
	}
}

collectJson('.');

for (const file of jsonFiles) {
	JSON.parse(fs.readFileSync(file, 'utf8'));
}

const workflow = fs.readFileSync('.github/workflows/qa.yml', 'utf8');
const parsedWorkflow = parseYaml(workflow);

if (!parsedWorkflow || typeof parsedWorkflow !== 'object' || !parsedWorkflow.jobs) {
	throw new Error('GitHub Actions workflow is not a valid job mapping.');
}

console.log(JSON.stringify({ result: 'pass', jsonFiles: jsonFiles.length, yamlFiles: 1 }));
