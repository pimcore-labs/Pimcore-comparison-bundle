import { defineConfig, type RsbuildPlugin } from '@rsbuild/core';
import { pluginReact } from '@rsbuild/plugin-react';
import { pluginModuleFederation } from '@module-federation/rsbuild-plugin';
import fs from 'fs';
import path from 'path';
import { v4 as uuidv4 } from 'uuid';

const PLUGIN_NAME = 'pimcore_comparison';

const BUILD_ID = uuidv4();
const OUTPUT_PATH = path.resolve(__dirname, '..', 'public', 'build', BUILD_ID);
const ASSET_PREFIX = `/bundles/pimcorecomparison/build/${BUILD_ID}/`;

function cleanOldBuilds(): void {
  const buildDir = path.resolve(__dirname, '..', 'public', 'build');
  if (fs.existsSync(buildDir)) {
    const entries = fs.readdirSync(buildDir, { withFileTypes: true });
    for (const entry of entries) {
      if (entry.isDirectory() && entry.name !== BUILD_ID) {
        fs.rmSync(path.join(buildDir, entry.name), { recursive: true, force: true });
      }
    }
  }
}

const pluginExposeRemote = (): RsbuildPlugin => ({
  name: 'expose-remote',
  setup(api) {
    api.onAfterBuild(() => {
      cleanOldBuilds();

      const remoteEntryUrl = `${ASSET_PREFIX}static/js/remoteEntry.js`;
      const exposeRemoteContent = `
      if (window.pluginRemotes === undefined) {
        window.pluginRemotes = {}
      }
      window.pluginRemotes.${PLUGIN_NAME} = "${remoteEntryUrl}"
    `;

      fs.writeFileSync(`${OUTPUT_PATH}/exposeRemote.js`, exposeRemoteContent, 'utf-8');

      const entrypointsPath = `${OUTPUT_PATH}/entrypoints.json`;
      if (fs.existsSync(entrypointsPath)) {
        const entrypoints = JSON.parse(fs.readFileSync(entrypointsPath, 'utf-8'));
        entrypoints.entrypoints = entrypoints.entrypoints || {};
        entrypoints.entrypoints['exposeRemote'] = {
          js: [`${ASSET_PREFIX}exposeRemote.js`],
          css: []
        };
        fs.writeFileSync(entrypointsPath, JSON.stringify(entrypoints, null, 2), 'utf-8');
      }
    });
  }
});

export default defineConfig({
  plugins: [
    pluginReact(),
    pluginModuleFederation({
      name: PLUGIN_NAME,
      filename: 'static/js/remoteEntry.js',
      dts: false,
      exposes: {
        '.': './js/src/index.ts',
      },
      remotes: {
        '@pimcore/studio-ui-bundle': `promise new Promise(resolve => {
          const proxy = {
            get: (request) => window['pimcore_studio_ui_bundle'].get(request),
            init: (...arg) => {
              try {
                return window['pimcore_studio_ui_bundle'].init(...arg)
              } catch(e) {
                console.log('remote container already initialized')
              }
            }
          }
          resolve(proxy)
        })`,
      },
      shared: {
        react: { singleton: true, requiredVersion: '^18.3.1' },
        'react-dom': { singleton: true, requiredVersion: '^18.3.1' },
        antd: { singleton: true, requiredVersion: '^5.22.0' },
        '@ant-design/icons': { singleton: true },
        inversify: { singleton: true },
        'reflect-metadata': { singleton: true },
      },
    }),
    pluginExposeRemote(),
  ],
  source: {
    entry: {
      main: './js/src/main.ts',
    },
  },
  output: {
    distPath: {
      root: OUTPUT_PATH,
      js: 'static/js',
      css: 'static/css',
    },
    filename: {
      js: '[name].[contenthash:8].js',
      css: '[name].[contenthash:8].css',
    },
    assetPrefix: ASSET_PREFIX,
    manifest: 'entrypoints.json',
  },
  tools: {
    bundlerChain: (chain) => {
      chain.output.uniqueName(PLUGIN_NAME);
    },
  },
  dev: {
    writeToDisk: true,
  },
});
