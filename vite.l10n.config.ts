/**
 * Wrapper around the application's Vite config for the non-writing l10n-ignore
 * analysis builds (`make l10n-ignore`). It only redirects the output directory
 * away from the app root, which Vite would otherwise warn about.
 */

import type { ConfigEnv, UserConfig, UserConfigExport } from 'vite';

import { mergeConfig } from 'vite';
import appConfig from './vite.config.ts';

export default async (env: ConfigEnv): Promise<UserConfig> => {
  const config = await (typeof appConfig === 'function' ? (appConfig as (env: ConfigEnv) => UserConfigExport)(env) : appConfig);
  return mergeConfig(config as UserConfig, { build: { outDir: 'build/l10n-analysis' } });
};
