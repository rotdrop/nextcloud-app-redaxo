/**
 * Redaxo -- a Nextcloud App for embedding Redaxo.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2020, 2021, 2023, 2025
 *
 * Redaxo is free software: you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * Redaxo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with Redaxo.  If not, see
 * <http://www.gnu.org/licenses/>.
 */

const tuneContents = (frame: HTMLIFrameElement) => {
  const frameWindow = frame.contentWindow!;
  const frameDocument = frameWindow.document!;

  console.info('FRAME ETC', {
    frame,
    frameWindow,
    frameDocument,
  })

  // Remove the logout stuff
  frameDocument.querySelector('#rex-js-nav-top')!.remove();

  // shift the entire thing a little bit into the inside
  frameDocument.querySelector('#rex-js-page-container')?.querySelectorAll('.rex-nav-main, .rex-page-main')
    .forEach((el) => (el as HTMLElement).style['padding-top'] = 0);

  // Make sure all external links are opened in another window
  frameDocument.querySelectorAll('a').forEach(
    (arg) => {
      const anchor = arg as HTMLAnchorElement;
      if (anchor.hostname && anchor.hostname !== window.location.hostname) {
        anchor.target = '_blank';
      }
    },
  );
};

/**
 * Fills height of window (more precise than height: 100%;)
 *
 * @param frame The frame to be  resized.
 */
const fillHeight = function(frame: HTMLIFrameElement) {
  const height = window.innerHeight - frame.getBoundingClientRect().top;
  frame.style.height = height + 'px';
  const outerDelta = frame.getBoundingClientRect().height - frame.clientHeight;
  if (outerDelta) {
    frame.style.height = (height - outerDelta) + 'px';
  }
};

/**
 * Fills width of window (more precise than width: 100%;)
 *
 * @param frame The frame to be resized.
 */
const fillWidth = function(frame: HTMLIFrameElement) {
  const width = window.innerWidth - frame.getBoundingClientRect().left;
  frame.style.width = width + 'px';
  const outerDelta = frame.getBoundingClientRect().width - frame.clientWidth;
  if (outerDelta > 0) {
    frame.style.width = (width - outerDelta) + 'px';
  }
};

/**
 * Fills height and width of RC window.
 * More precise than height/width: 100%.
 *
 * @param frame TBD.
 */
const resizeIframe = function(frame: HTMLIFrameElement) {
  fillHeight(frame);
  fillWidth(frame);
};

/**
 * @param frame TBD.
 */
const loadHandler = function(frame: HTMLIFrameElement) {
  tuneContents(frame)
  resizeIframe(frame);
};

export { loadHandler, resizeIframe as resizeHandler };
