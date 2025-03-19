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
  <NcContent :app-name="appName" class="app-container">
    <div ref="loaderContainer" class="loader-container" />
    <iframe :id="frameId"
            ref="externalFrame"
            :src="externalLocation"
            :name="appName"
            @load="loadHandlerWrapper"
    />
  </NcContent>
</template>
<script setup lang="ts">
import { appName } from './config.ts'
import {
  // NcAppContent,
  // NcAppNavigation,
  NcContent,
} from '@nextcloud/vue'
import {
  computed,
  onMounted,
  onUnmounted,
  ref,
} from 'vue'
import { loadHandler, resizeHandler } from './redaxo.ts'
import getInitialState from './toolkit/util/initial-state.ts'

interface InitialState {
  externalLocation: string,
}

const initialState = getInitialState<InitialState>({ section: 'page' })

console.info('GOT INITIAL_STATE', { initialState })

const externalLocation = computed(() => initialState?.externalLocation)

let gotLoadEvent = false

const loaderContainer = ref<null|HTMLElement>(null)
const externalFrame = ref<null|HTMLIFrameElement>(null)

const loadHandlerWrapper = () => {
  console.trace('ROUNDCUBD: GOT LOAD EVENT')
  loadHandler(externalFrame.value!)
  if (!gotLoadEvent) {
    loaderContainer.value!.classList.add('fading')
  }
  gotLoadEvent = true
}

const resizeHandlerWrapper = () => {
  resizeHandler(externalFrame.value!)
}

const loadTimeout = 1000 // 1 second
let timerCount = 0

const loadTimerHandler = () => {
  if (gotLoadEvent) {
    return
  }
  timerCount++
  const rcfContents = externalFrame.value!.contentWindow!.document
  if (rcfContents.querySelector('#layout')) {
    console.info('REDAXO: LOAD EVENT FROM TIMER AFTER ' + (loadTimeout * timerCount) + ' ms')
    externalFrame.value!.dispatchEvent(new Event('load'))
  } else {
    setTimeout(loadTimerHandler, loadTimeout)
  }
}

const frameId = computed(() => appName + 'Frame')

onMounted(() => {
  window.addEventListener('resize', resizeHandlerWrapper)
  setTimeout(loadTimerHandler, loadTimeout)
})

onUnmounted(() => {
  window.removeEventListener('resize', resizeHandlerWrapper)
})

</script>
<style scoped lang="scss">
.app-container {
  display: flex;
  flex-direction: column;
  flex-wrap: wrap;
  justify-content: center;
  align-items: stretch;
  align-content: stretch;
  &.error {
    .loader-container {
      display:none; // do not further annoy the user
    }
  }
  .loader-container {
    background-image: url('../img/loader.gif');
    background-repeat: no-repeat;
    background-position: center;
    z-index:10;
    width:100%;
    height:100%;
    position:fixed;
    transition: visibility 1s, opacity 1s;
    &.fading {
      opacity: 0;
      visibility: hidden;
    }
  }
  #errorMsg {
    align-self: center;
    padding:2em 2em;
    font-weight: bold;
    font-size:120%;
    max-width: 80%;
    border: 2px solid var(--color-border-maxcontrast);
    border-radius: var(--border-radius-pill);
    background-color: var(--color-background-dark);
  }
  iframe {
    flex-grow: 10;
  }
}
</style>
