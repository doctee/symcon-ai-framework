import crypto from 'node:crypto';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import process from 'node:process';
import {fileURLToPath} from 'node:url';
import {build} from 'esbuild';

const browserDirectory = path.dirname(fileURLToPath(import.meta.url));
const candidateDirectory = path.resolve(
    browserDirectory,
    '../candidate/openlayers'
);
const entryFile = path.join(browserDirectory, 'src/openlayers-renderer.js');
const checkOnly = process.argv.includes('--check');
const temporaryRoot = fs.mkdtempSync(
    path.join(os.tmpdir(), 'saef-owntracks-openlayers-')
);
const temporaryOutput = path.join(temporaryRoot, 'openlayers');
fs.mkdirSync(temporaryOutput, {recursive: true});

function sha256(file) {
    return crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
}

function packageNameFromInput(input) {
    const normalized = input.replaceAll('\\', '/');
    const marker = 'node_modules/';
    const markerIndex = normalized.lastIndexOf(marker);
    if (markerIndex < 0) {
        return null;
    }
    const remainder = normalized.slice(markerIndex + marker.length);
    const parts = remainder.split('/');
    return parts[0].startsWith('@')
        ? parts.slice(0, 2).join('/')
        : parts[0];
}

function packageRoot(packageName) {
    return path.join(browserDirectory, 'node_modules', ...packageName.split('/'));
}

function licenseFile(packageName) {
    const root = packageRoot(packageName);
    const match = fs.readdirSync(root)
        .sort()
        .find((file) => /^licen[cs]e(?:\.|$)/i.test(file));
    if (!match) {
        throw new Error('No license file found for ' + packageName);
    }
    return path.join(root, match);
}

function safePackageName(packageName) {
    return packageName.replace('@', '').replaceAll('/', '--');
}

function listFiles(directory, prefix = '') {
    const files = [];
    for (const entry of fs.readdirSync(directory, {withFileTypes: true})) {
        const relative = path.join(prefix, entry.name);
        if (entry.isDirectory()) {
            files.push(...listFiles(path.join(directory, entry.name), relative));
        } else {
            files.push(relative);
        }
    }
    return files.sort();
}

function compareDirectories(expected, actual) {
    if (!fs.existsSync(actual)) {
        return false;
    }
    const expectedFiles = listFiles(expected);
    const actualFiles = listFiles(actual);
    if (JSON.stringify(expectedFiles) !== JSON.stringify(actualFiles)) {
        return false;
    }
    return expectedFiles.every((file) => fs.readFileSync(path.join(expected, file))
        .equals(fs.readFileSync(path.join(actual, file))));
}

try {
    const outputJavaScript = path.join(
        temporaryOutput,
        'openlayers-renderer.bundle.js'
    );
    const buildResult = await build({
        entryPoints: [entryFile],
        outfile: outputJavaScript,
        bundle: true,
        minify: true,
        format: 'iife',
        target: ['es2020'],
        charset: 'utf8',
        legalComments: 'external',
        metafile: true,
        sourcemap: false,
        logLevel: 'silent',
    });

    const runtimePackageNames = [...new Set(
        Object.keys(buildResult.metafile.inputs)
            .map(packageNameFromInput)
            .filter((name) => name !== null)
    )].sort();
    const licenseDirectory = path.join(temporaryOutput, 'licenses');
    fs.mkdirSync(licenseDirectory, {recursive: true});
    const runtimePackages = runtimePackageNames.map((packageName) => {
        const packageJson = JSON.parse(
            fs.readFileSync(path.join(packageRoot(packageName), 'package.json'), 'utf8')
        );
        const licenseTarget = path.join(
            licenseDirectory,
            safePackageName(packageName) + '.txt'
        );
        fs.copyFileSync(licenseFile(packageName), licenseTarget);
        return {
            name: packageName,
            version: packageJson.version,
            license: packageJson.license,
            licenseFile: path.relative(temporaryOutput, licenseTarget),
        };
    });

    const esbuildPackage = JSON.parse(
        fs.readFileSync(
            path.join(browserDirectory, 'node_modules/esbuild/package.json'),
            'utf8'
        )
    );
    const esbuildLicenseTarget = path.join(
        licenseDirectory,
        'esbuild-build-tool.txt'
    );
    fs.copyFileSync(licenseFile('esbuild'), esbuildLicenseTarget);

    const artifactFiles = listFiles(temporaryOutput)
        .filter((file) => file !== 'bundle-manifest.json')
        .map((file) => ({
            file,
            bytes: fs.statSync(path.join(temporaryOutput, file)).size,
            sha256: sha256(path.join(temporaryOutput, file)),
        }));
    const manifest = {
        schemaVersion: 1,
        entry: 'browser/src/openlayers-renderer.js',
        runtimePackages,
        buildTool: {
            name: 'esbuild',
            version: esbuildPackage.version,
            license: esbuildPackage.license,
            licenseFile: path.relative(temporaryOutput, esbuildLicenseTarget),
        },
        artifacts: artifactFiles,
    };
    fs.writeFileSync(
        path.join(temporaryOutput, 'bundle-manifest.json'),
        JSON.stringify(manifest, null, 2) + '\n'
    );

    if (checkOnly) {
        if (!compareDirectories(temporaryOutput, candidateDirectory)) {
            process.stderr.write('OpenLayers bundle differs from generated output.\n');
            process.exitCode = 1;
        } else {
            process.stdout.write('OpenLayers bundle is current.\n');
        }
    } else {
        fs.rmSync(candidateDirectory, {recursive: true, force: true});
        fs.cpSync(temporaryOutput, candidateDirectory, {recursive: true});
        process.stdout.write('OpenLayers bundle generated.\n');
    }
} finally {
    fs.rmSync(temporaryRoot, {recursive: true, force: true});
}
