const fc = require('fast-check');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

const seed = Number.parseInt(process.env.PIXCENSUS_PROPERTY_SEED || '20260801', 10);
const numRuns = Number.parseInt(process.env.PIXCENSUS_PROPERTY_RUNS || '500', 10);

if (!Number.isInteger(seed) || !Number.isInteger(numRuns) || numRuns < 1 || numRuns > 2000) {
	throw new Error('PIXCENSUS_PROPERTY_SEED and PIXCENSUS_PROPERTY_RUNS must be bounded integers.');
}

const boundedText = fc.string({ maxLength: 512 });
const edgeText = fc.constantFrom(
	'',
	'\u0000',
	'\r\n',
	'例.example',
	'emoji-😀.example',
	'a'.repeat(4097),
	'b'.repeat(8193)
);
const aliasList = fc
	.array(fc.string({ minLength: 1, maxLength: 63 }), { maxLength: 25 })
	.map((aliases) => aliases.join(','));
const rewriteList = fc
	.array(fc.tuple(boundedText, boundedText), { maxLength: 25 })
	.map((rules) => rules.map(([from, to]) => `${from} => ${to}`).join('\n'));
const formulaValue = fc
	.tuple(fc.constantFrom('=', '+', '-', '@', ' \t=', '\t', '\r', '\n'), boundedText)
	.map(([prefix, value]) => prefix + value);

const generatedCases = fc.sample(
	fc.record({
		aliases: fc.oneof(boundedText, edgeText, aliasList),
		rewrites: fc.oneof(boundedText, edgeText, rewriteList),
		csv: fc.oneof(boundedText, edgeText, formulaValue),
	}),
	{ seed, numRuns }
);

const phpBinary = process.env.PHP_BINARY || 'php';
const harness = path.join(__dirname, 'security-inputs-harness.php');
const execution = spawnSync(phpBinary, [harness], {
	input: JSON.stringify({ cases: generatedCases }),
	encoding: 'utf8',
	maxBuffer: 4 * 1024 * 1024,
});

if (execution.error) {
	throw execution.error;
}

if (execution.status !== 0) {
	throw new Error(`PHP property harness failed: ${execution.stderr || execution.stdout}`);
}

const result = JSON.parse(execution.stdout);

if (result.result !== 'pass' || result.cases !== numRuns) {
	throw new Error(`Unexpected PHP property harness result: ${execution.stdout}`);
}

console.log(
	JSON.stringify({
		result: 'pass',
		seed,
		cases: numRuns,
		assertions: result.assertions,
		production: ['PIXCENSUS_CDN_Settings::validate', 'PIXCENSUS_CSV::neutralize_formula'],
	})
);
