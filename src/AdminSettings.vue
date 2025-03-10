<!--
 - Redaxo -- a Nextcloud App for embedding Redaxo.
 -
 - @author Claus-Justus Heine <himself@claus-justus-heine.de>
 - @copyright Copyright (c) 2023, 2024, 2025 Claus-Justus Heine
 - @license AGPL-3.0-or-later
 -
 - Redaxo is free software: you can redistribute it and/or
 - modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 - License as published by the Free Software Foundation; either
 - version 3 of the License, or (at your option) any later version.
 -
 - Redaxo is distributed in the hope that it will be useful,
 - but WITHOUT ANY WARRANTY; without even the implied warranty of
 - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 - GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 -
 - You should have received a copy of the GNU Affero General Public
 - License along with Redaxo.  If not, see
 - <http://www.gnu.org/licenses/>.
 -->
<template>
  <div :class="['templateroot', appName, ...cloudVersionClasses]">
    <h1 class="title">
      {{ t(appName, 'Redaxo Integration') }}
    </h1>
    <NcSettingsSection title="">
      <TextField :value.sync="settings.externalLocation"
                 :label="t(appName, 'Redaxo Installation Path')"
                 :hint="t(appName, 'Please enter the location of the already installed Redaxo instance. This should either be a path, absolute or relative to the root of the web server, or a complete URL pointing to the web location of the Redaxo. In order to make things work, your have to enable the XML-RPC protocol in your Redaxo.')"
                 :disabled="loading > 0"
                 @submit="saveTextInput('externalLocation')"
      />
    </NcSettingsSection>
    <NcSettingsSection title="">
      <TextField :value.sync="settings.authenticationRefreshInterval"
                 :label="t(appName, 'Redaxo Session Refresh Interval [s]')"
                 :hint="t(appName, 'Please enter the desired session-refresh interval here. The interval is measured in seconds and should be somewhat smaller than the configured session life-time for the Redaxo instance in use.')"
                 :disabled="loading > 0"
                 @update="saveTextInput('authenticationRefreshInterval')"
      />
    </NcSettingsSection>
    <NcSettingsSection title="">
      <input id="enable-ssl-verify"
             v-model="settings.enableSSLVerify"
             class="checkbox"
             type="checkbox"
             name="enableSSLVerify"
             value="1"
             :disabled="loading > 0"
             @change="saveSetting('enableSSLVerify')"
      >
      <label for="enable-ssl-verify"
             :title="t(appName, 'Disable SSL verification, e.g. for self-signed certificates or known mis-matching host-names like \'localhost\'.')"
      >
        {{ t(appName, 'Enable SSL verification.') }}
      </label>
      <p class="hint">
        {{ t(appName, 'Disable SSL verification, e.g. for self-signed certificates or known mis-matching host-names like \'localhost\'.') }}
      </p>
    </NcSettingsSection>
  </div>
</template>
<script setup lang="ts">
import { appName } from './config.ts'
import { translate as t } from '@nextcloud/l10n'
import { NcSettingsSection } from '@nextcloud/vue'
import TextField from '@rotdrop/nextcloud-vue-components/lib/components/TextFieldWithSubmitButton.vue'
import cloudVersionClassesImport from './toolkit/util/cloud-version-classes.js'
import {
  fetchSettings,
  saveConfirmedSetting,
  saveSimpleSetting,
} from './toolkit/util/settings-sync.ts'
import {
  ref,
  computed,
} from 'vue'

const loading = ref(0)
const cloudVersionClasses = computed<string[]>(() => cloudVersionClassesImport)

const settings = ref({
  externalLocation: '',
  enableSSLVerify: false,
  authenticationRefreshInterval: 0,
})

// slurp in all personal settings
const getData = async () => {
  ++loading.value
  return fetchSettings({ section: 'admin', settings }).finally(() => {
    console.info('THIS', this)
    --loading.value
  })
}
getData()

const saveTextInput = async (settingsKey: string, value?: string | number | boolean, force?: boolean) => {
  if (value === undefined) {
    value = settings[settingsKey] || ''
  }
  if (loading.value > 0) {
    // avoid ping-pong by reactivity
    console.info('SKIPPING SETTINGS-SAVE DURING LOAD', settingsKey, value)
    return
  }
  return saveConfirmedSetting({ value, section: 'admin', settingsKey, force, settings, resetData: getData })
}

const saveSetting = async (settingsKey: string) => {
  if (loading.value > 0) {
    // avoid ping-pong by reactivity
    console.info('SKIPPING SETTINGS-SAVE DURING LOAD', settingsKey)
    return
  }
  saveSimpleSetting({ settingsKey, section: 'admin', settings })
}
</script>
<style lang="scss" scoped>
.cloud-version {
  --cloud-icon-info: var(--icon-info-000);
  --cloud-icon-checkmark: var(--icon-checkmark-000);
  --cloud-icon-alert: var(--icon-alert-outline-000);
  --cloud-theme-filter: none;
  &.cloud-version-major-25 {
    --cloud-icon-info: var(--icon-info-dark);
    --cloud-icon-checkmark: var(--icon-checkmark-dark);
    --cloud-icon-alert: var(--icon-alert-outline-dark);
    --cloud-theme-filter: var(--background-invert-if-dark);
  }
}
.flex-container {
  display:flex;
  &.flex-column {
    flex-direction:column;
  }
  &.flex-row {
    flex-direction:row;
  }
  &.flex-center {
    align-items:center;
  }
}
.settings-section {
  :deep(.app-settings-section) {
    margin-bottom: 40px;
  }
  :deep(.settings-section__title) {
    position: relative;
    padding-left:48px;
    height:32px;
    &::before {
      content: "";
      position: absolute;
      left: 0;
      top: 0;
      width: 32px;
      height: 32px;
      background-size:32px;
      background-image:url('../img/app.svg');
      background-repeat:no-repeat;
      background-origin:border-box;
      background-position:left center;
      filter: var(--cloud-theme-filter);
    }
  }
}
.hint {
  color: var(--color-text-lighter);
  font-style: italic;
  max-width: 400px;
}
</style>
