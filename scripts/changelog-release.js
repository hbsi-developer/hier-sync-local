// Runs from `npm version` (the "version" script): moves the "Unreleased" notes in CHANGELOG.md
// under a heading for the new version, then npm commits and tags it.
const fs = require('fs');
const path = require('path');

const file = path.join(__dirname, '..', 'CHANGELOG.md');
const version = process.env.npm_package_version || require('../package.json').version;
if (!fs.existsSync(file) || !version) process.exit(0);

const date = new Date().toISOString().slice(0, 10);
const text = fs.readFileSync(file, 'utf8');
if (!text.includes('## [Unreleased]')) process.exit(0);

fs.writeFileSync(file, text.replace('## [Unreleased]', `## [Unreleased]\n\n## [${version}] - ${date}`));
console.log(`CHANGELOG.md: released ${version}`);
