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
  <NcContent :app-name="appName">
    <NcAppContent :class="[appName + '-content-container', { 'icon-loading': loading }]">
      <RouterView v-show="!loading"
                  :loading.sync="loading"
                  @iframe-loaded="onIFrameLoaded($event)"
      />
    </NcAppContent>
  </NcContent>
</template>
<script setup lang="ts">
import { appName } from './config.ts'
import {
  NcAppContent,
  // NcAppNavigation,
  NcContent,
} from '@nextcloud/vue'
import {
  ref,
} from 'vue'
import {
  useRoute,
  useRouter,
} from 'vue-router/composables'
import type { Location as RouterLocation } from 'vue-router'

const loading = ref(true)

const router = useRouter()
const currentRoute = useRoute()

const onIFrameLoaded = async (event: { wikiPath: string[], query: Record<string, string> }) => {
  loading.value = false
  console.debug('GOT EVENT', { event })
  const routerLocation: RouterLocation = {
    name: currentRoute.name!,
    params: {},
    query: { ...event.query },
  }
  try {
    await router.push(routerLocation)
  } catch (error) {
    console.debug('NAVIGATION ABORTED', { error })
  }
}

// The initial route is not named and consequently does not load the
// wrapper component, so just replace it by the one and only named
// route.
router.onReady(async () => {
  if (!currentRoute.name) {
    const routerLocation: RouterLocation = {
      name: 'home',
      params: {},
      query: { ...currentRoute.query },
    }
    try {
      await router.replace(routerLocation)
    } catch (error) {
      console.debug('NAVIGATION ABORTED', { error })
    }
  }
})
</script>
<style scoped lang="scss">
  main {
  // strange: all divs have the same height, there is no horizontal
  // scrollbar, but still FF likes to emit a vertical scrollbar.
  //
  // DO NOT ALLOW THIS!
  overflow: hidden !important;
}
</style>
