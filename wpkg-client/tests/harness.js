'use strict';

/**
 * Loads wpkg.js in a Node.js VM with stubs of the Windows Script Host objects it uses.
 *
 * wpkg.js runs main() when loaded, so the harness removes that single call. Every other line
 * is the shipped script: tests exercise its real functions and globals.
 */

const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const SCRIPT_PATH = path.join(__dirname, '..', 'wpkg.js');

/**
 * Builds a fake XML element.
 *
 * @param {string} name Element name.
 * @param {Object<string, string>} attributes Attributes.
 * @param {Array<Object>} children Child elements.
 * @returns {Object}
 */
function element(name, attributes = {}, children = []) {
    return {
        nodeName: name,
        tagName: name,
        attributes,
        children,
        getAttribute(attribute) {
            return Object.hasOwn(this.attributes, attribute) ? this.attributes[attribute] : null;
        },
        setAttribute(attribute, value) {
            this.attributes[attribute] = value;
        },
        selectNodes(query) {
            return toNodeList(this.children.filter((child) => matches(child, query)));
        },
        selectSingleNode(query) {
            return this.children.find((child) => matches(child, query)) ?? null;
        },
        cloneNode() {
            return element(name, { ...this.attributes }, [...this.children]);
        },
    };
}

/**
 * Tells whether an element matches the small XPath subset used by wpkg.js.
 *
 * @param {Object} node Element.
 * @param {string} query XPath expression.
 * @returns {boolean}
 */
function matches(node, query) {
    const attributeQuery = query.match(/^(\w+)\[@(\w+)='([^']*)'\]$/);
    if (attributeQuery) {
        const [, name, attribute, value] = attributeQuery;
        return node.nodeName === name && node.getAttribute(attribute) === value;
    }

    return node.nodeName === query.replace(/^wpkg:/, '');
}

/**
 * Gives an array the indexed `item()` access of an MSXML node list.
 *
 * @param {Array<Object>} nodes Elements.
 * @returns {Array<Object>}
 */
function toNodeList(nodes) {
    nodes.item = (index) => nodes[index];

    return nodes;
}

/**
 * Loads a fresh copy of wpkg.js.
 *
 * @param {Object} options Fake machine.
 * @param {string} options.hostname Machine name.
 * @param {Object<string, string>} options.env Environment variables, used by %VAR% expansion.
 * @param {Array<string>} options.files Existing file paths.
 * @param {Object<string, string>} options.fileVersions File versions by path.
 * @param {Object<string, number>} options.fileSizes File sizes by path.
 * @param {Object<string, string>} options.registry Registry values by key.
 * @returns {Object} The VM context: wpkg.js functions and globals are its properties.
 */
function loadWpkg({ hostname = 'pc01', env = {}, files = [], fileVersions = {}, fileSizes = {}, registry = {} } = {}) {
    const expand = (text) => String(text).replace(/%([^%]+)%/g, (found, name) => env[name] ?? found);

    const shell = {
        ExpandEnvironmentStrings: expand,
        RegRead(key) {
            if (!Object.hasOwn(registry, key)) {
                // wpkg.js recognizes a missing key by comparing `description` with a probe error.
                throw Object.assign(new Error('Key not found'), {
                    description: `Unable to open registry key "${key}" for reading.`,
                });
            }

            return registry[key];
        },
        Environment: () => createEnvironment(env),
    };

    const fileSystem = {
        FileExists: (filePath) => files.includes(filePath),
        FolderExists: () => false,
        GetFileVersion: (filePath) => fileVersions[filePath] ?? '',
        GetFile: (filePath) => ({ Size: fileSizes[filePath] ?? 0 }),
        GetParentFolderName: (filePath) => path.win32.dirname(filePath),
        BuildPath: (base, name) => path.win32.join(base, name),
    };

    const stubs = {
        'WScript.Shell': shell,
        'Scripting.FileSystemObject': fileSystem,
    };

    const ActiveXObject = function ActiveXObject(progId) {
        if (progId === 'Scripting.Dictionary') {
            return createDictionary();
        }
        if (!Object.hasOwn(stubs, progId)) {
            throw new Error(`Unexpected ActiveXObject: ${progId}`);
        }

        return stubs[progId];
    };

    const output = [];
    const context = vm.createContext({
        ActiveXObject,
        GetObject() {
            throw new Error('GetObject is not available in tests');
        },
        Enumerator: createEnumerator,
        WScript: {
            ScriptFullName: 'C:\\wpkg\\wpkg.js',
            Echo: (message) => output.push(String(message)),
            Quit() {},
            CreateObject: (progId) => (progId === 'WScript.Network' ? { ComputerName: hostname } : shell),
            Arguments: { Named: { Item: () => null, Exists: () => false }, Unnamed: { length: 0 } },
        },
    });

    const source = fs.readFileSync(SCRIPT_PATH, 'utf8');
    const bootless = source.replace(/^try \{\r?\n\tmain\(\);/m, 'try {');
    if (bootless === source) {
        throw new Error('The main() call of wpkg.js was not found');
    }
    vm.runInContext(bootless, context, { filename: 'wpkg.js' });

    // WMI provides these on Windows. wpkg.js caches them in globals, which tests can preset.
    context.hostAttributes = createDictionary({ hostname, os: 'microsoft windows 10 pro', architecture: 'x64' });
    context.ipAddresses = ['10.0.0.1'];

    // Logging needs the event log and a log file. setEnv() relies on `procEnv(key) = value`,
    // JScript-only syntax that fails at run time on V8.
    vm.runInContext(
        'dinfo = function () {}; error = function () {}; warning = function () {}; info = function () {};'
        + 'setEnv = function () {};',
        context,
    );

    return context;
}

/**
 * Builds the process environment collection: callable by name, enumerable as KEY=VALUE strings.
 *
 * @param {Object<string, string>} env Environment variables.
 * @returns {Function}
 */
function createEnvironment(env) {
    const collection = (name) => env[name] ?? '';
    const lines = Object.entries(env).map(([name, value]) => `${name}=${value}`);

    Object.assign(collection, lines, { Remove() {} });
    Object.defineProperty(collection, 'length', { value: lines.length });

    return collection;
}

/**
 * Builds a minimal JScript Enumerator over a node list.
 *
 * @param {Array<Object>} collection Items.
 * @returns {Object}
 */
function createEnumerator(collection) {
    let position = 0;

    return {
        atEnd: () => position >= collection.length,
        item: () => collection[position],
        moveFirst() {
            position = 0;
        },
        moveNext() {
            position += 1;
        },
    };
}

/**
 * Builds a minimal Scripting.Dictionary. COM names are case-insensitive: wpkg.js uses both forms.
 *
 * @param {Object<string, *>} initial Initial entries.
 * @returns {Object}
 */
function createDictionary(initial = {}) {
    const entries = new Map(Object.entries(initial));
    const keys = () => ({ toArray: () => [...entries.keys()] });

    return {
        Exists: (key) => entries.has(key),
        Add: (key, value) => entries.set(key, value),
        add: (key, value) => entries.set(key, value),
        Item: (key) => entries.get(key) ?? null,
        Remove: (key) => entries.delete(key),
        Keys: keys,
        keys,
        get Count() {
            return entries.size;
        },
    };
}

module.exports = { createDictionary, element, loadWpkg, toNodeList };
