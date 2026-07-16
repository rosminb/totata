/**
 * This file is part of Totara Enterprise Extensions.
 *
 * Copyright (C) 2020 onwards Totara Learning Solutions LTD
 *
 * Totara Enterprise Extensions is provided only to Totara
 * Learning Solutions LTD's customers and partners, pursuant to
 * the terms and conditions of a separate agreement with Totara
 * Learning Solutions LTD or its affiliate.
 *
 * If you do not have an agreement with Totara Learning Solutions
 * LTD, you may not access, use, modify, or distribute this software.
 * Please contact [licensing@totaralearning.com] for more information.
 *
 * @author Kian Nguyen <kian.nguyen@totaralearning.com>
 * @module editor_weka
 */

import BaseExtension from './Base';
import { ToolbarItem } from '../toolbar';
import { langString, loadLangStrings } from 'tui/i18n';
import { pickFiles, UploadError } from '../utils/upload';
import ImageBlock from 'editor_weka/components/nodes/ImageBlock';
import VideoBlock from 'editor_weka/components/nodes/VideoBlock';
import AudioBlock from 'editor_weka/components/nodes/AudioBlock';
import ImageIcon from 'tui/components/icons/Image';
import { IMAGE, VIDEO } from '../helpers/media';
import { getJsonAttrs } from './util';
import { notify } from 'tui/notifications';

class MediaExtension extends BaseExtension {
  /**
   *
   * @param {Object} opt
   */
  constructor(opt) {
    super(opt);
    this.acceptTypes = opt.accept_types || [];
  }

  nodes() {
    return {
      image: {
        schema: {
          atom: true,
          group: 'block',
          inline: false,
          selectable: true,
          attrs: {
            filename: { default: undefined },
            // This is from the support of file_rewrite_plugin_file, it will be picked up by the
            // server side to reformat the plugin file url.
            url: { default: undefined },
            alttext: { default: undefined },
          },

          parseDOM: [
            {
              tag: 'div.tui-wekaNodeImageBlock',
              getAttrs: getJsonAttrs,
            },
            {
              tag: 'div.tui-imageBlock',
              getAttrs: getJsonAttrs,
            },
          ],

          toDOM(node) {
            return [
              'div',
              {
                class: 'tui-wekaNodeImageBlock',
                'data-attrs': JSON.stringify({
                  filename: node.attrs.filename,
                  alttext: node.attrs.alttext,
                  url: node.attrs.url,
                }),
              },
            ];
          },
        },
        component: ImageBlock,
        componentContext: {
          replaceWithAttachment: this._replaceImageWithAttachment.bind(this),
          updateImage: this._updateImage.bind(this),
          hasAttachmentNode: this._hasAttachmentsNode.bind(this),
          removeNode: this.removeNode.bind(this),
          getItemId: this._getItemId.bind(this),
          getDownloadUrl: this._getDownloadUrl.bind(this),
        },
      },

      video: {
        schema: {
          atom: true,
          group: 'block',
          inline: false,
          attrs: {
            filename: { default: undefined },
            // This is from the support of file_rewrite_plugin_file, it will be picked up by the
            // server side to reformat the plugin file url.
            url: { default: undefined },
            mime_type: { default: undefined },
            subtitle: { default: undefined },
          },

          parseDOM: [
            {
              tag: 'div.tui-wekaNodeVideoBlock',
              getAttrs: getJsonAttrs,
            },
            {
              tag: 'div.tui-videoBlock',
              getAttrs: getJsonAttrs,
            },
          ],

          toDOM(node) {
            let dataAttrs = {
              filename: node.attrs.filename,
              url: node.attrs.url,
              mime_type: node.attrs.mime_type,
              subtitle: node.attrs.subtitle,
            };

            return [
              'div',
              {
                class: 'tui-wekaNodeVideoBlock',
                'data-attrs': JSON.stringify(dataAttrs),
              },
            ];
          },
        },
        component: VideoBlock,
        componentContext: {
          replaceWithAttachment: this._replaceVideoWithAttachment.bind(this),
          hasAttachmentNode: this._hasAttachmentNode.bind(this),
          removeNode: this.removeNode.bind(this),
          /** @deprecated since Totara 13.3 */
          getFileUrl: () => null,
          getItemId: this._getItemId.bind(this),
          getDownloadUrl: this._getDownloadUrl.bind(this),
          getContextId: this._getContextId.bind(this),
          updateVideoWithSubtitle: this._updateVideoWithSubtitle.bind(this),
        },
      },

      audio: {
        schema: {
          atom: true,
          group: 'block',
          inline: false,
          attrs: {
            filename: { default: undefined },
            // This is from the support of file_rewrite_plugin_file, it will be picked up by the
            // server side to reformat the plugin file url.
            url: { default: undefined },

            // This is needed to display embedded audio file.
            mime_type: { default: undefined },
            transcript: { default: undefined },
          },

          parseDOM: [
            {
              tag: 'div.tui-wekaNodeAudioBlock',
              getAttrs: getJsonAttrs,
            },
            {
              tag: 'div.tui-audioBlock',
              getAttrs: getJsonAttrs,
            },
          ],

          toDOM(node) {
            return [
              'div',
              {
                class: 'tui-wekaNodeAudioBlock',
                'data-attrs': JSON.stringify({
                  filename: node.attrs.filename,
                  url: node.attrs.url,
                  mime_type: node.attrs.mime_type,
                  transcript: node.attrs.transcript,
                }),
              },
            ];
          },
        },

        component: AudioBlock,
        componentContext: {
          hasAttachmentNode: this._hasAttachmentNode.bind(this),
          replaceWithAttachment: this._replaceAudioWithAttachment.bind(this),
          removeNode: this.removeNode.bind(this),
          /** @deprecated since Totara 13.3 */
          getFileUrl: () => null,
          getItemId: this._getItemId.bind(this),
          getDownloadUrl: this._getDownloadUrl.bind(this),
          getContextId: this._getContextId.bind(this),
          updateAudioWithTranscript: this._updateAudioWithTranscript.bind(this),
        },
      },
    };
  }

  toolbarItems() {
    if (!this.editor.fileStorage.enabled) return [];
    return [
      new ToolbarItem({
        group: 'embeds',
        label: langString('embedded_media', 'editor_weka'),
        iconComponent: ImageIcon,
        execute: editor => {
          pickFiles(editor).then(
            /**
             *
             * @param {FileList|Array} files
             * @return {Promise<void>}
             */
            async files => {
              if (files) {
                this._startUploading(files);
              }
            }
          );
        },
      }),
    ];
  }

  /**
   *
   * @param {FileList|Array} files
   * @return {Promise<void>}
   * @private
   */
  async _startUploading(files) {
    try {
      const submitFiles = await this.editor.fileStorage.uploadFiles(
        files,
        this.acceptTypes
      );

      if (submitFiles.length === 0) {
        return;
      }

      const schema = this.editor.state.schema;
      const images = await Promise.all(
        submitFiles.map(({ filename, url, media_type, mime_type }) => {
          if (IMAGE === media_type) {
            return schema.node('image', {
              filename: filename,
              alttext: null,
              url: url,
            });
          } else if (VIDEO === media_type) {
            return schema.node('video', {
              filename: filename,
              url: url,
              mime_type: mime_type,
              subtitle: null,
            });
          } else {
            return schema.node('audio', {
              url: url,
              filename: filename,
              mime_type: mime_type,
              transcript: null,
            });
          }
        })
      );

      this.editor.execute((state, dispatch) => {
        const transaction = state.tr,
          { selection } = transaction;

        // First we need to check whether the current selection is in paragraph or not.
        if (this._hasAttachmentsNode()) {
          // Check if the attachments node is appearing in the schema.
          // If there is we will search for whether the current cursor is in it or not.
          const { attachments } = schema.nodes;

          if (
            selection.$from.parent &&
            attachments.name === selection.$from.parent.type.name
          ) {
            // Parent is in attachments block. Need to move to the end of this node.
            transaction.insert(transaction.selection.$from.after(), images);

            dispatch(transaction);
            return;
          }
        }

        if (selection.empty) {
          let from = selection.$from,
            node = from.parent;

          if (node.type.name === 'paragraph' && node.content.size === 0) {
            // Check that if it is an empty paragraph, then we will remove it.
            transaction.replaceWith(from.before(), from.after(), images);
            this.editor.view.focus();
          } else {
            transaction.insert(from.pos, images);
          }
        } else {
          transaction.replaceWith(selection.from, selection.to, images);
        }

        dispatch(transaction);
      });
    } catch (e) {
      console.error(e);
      if (e instanceof UploadError) {
        notify({ type: 'error', message: e.message });
      } else {
        const str = langString('error_upload_failed', 'editor_weka');
        loadLangStrings([str]).then(() =>
          notify({ type: 'error', message: str.toString() })
        );
      }
    }
  }

  /**
   *
   * @param {Function}  getRange
   * @param {String}    filename
   * @param {String}    alttext
   *
   * @private
   */
  async _replaceImageWithAttachment(getRange, { filename, alttext, size }) {
    const info = await this._getFileInfo(filename);

    this.editor.execute((state, dispatch) => {
      const transaction = state.tr,
        range = getRange();

      let attachment = state.schema.node('attachment', {
        filename: filename,
        url: info.url,
        size: size,
        option: {
          alttext: alttext,
        },
      });

      dispatch(
        transaction.replaceWith(
          range.from,
          range.to,
          state.schema.node('attachments', null, [attachment])
        )
      );
    });
  }

  /**
   *
   * @param {Function}  getRange
   * @param {String}    filename
   * @param {Number}    size
   * @param {?Object}   subtitle
   * @private
   */
  async _replaceVideoWithAttachment(getRange, { filename, size, subtitle }) {
    const info = await this._getFileInfo(filename);

    this.editor.execute((state, dispatch) => {
      const transaction = state.tr,
        range = getRange();

      let attachmentAttrs = {
        filename: filename,
        url: info.url,
        size: size,
        option: {},
      };

      if (subtitle) {
        attachmentAttrs.option.subtitle = {
          url: subtitle.url,
          filename: subtitle.filename,
        };
      }

      let attachment = state.schema.node('attachment', attachmentAttrs);

      dispatch(
        transaction.replaceWith(
          range.from,
          range.to,
          state.schema.node('attachments', null, [attachment])
        )
      );
    });
  }

  /**
   *
   * @param {Function}    getRange
   * @param {String}      filename
   * @param {String}      url
   * @param {String}      mime_type
   * @param {Object|null} subtitle
   * @return {Promise<void>}
   * @private
   */
  async _updateVideoWithSubtitle(
    getRange,
    { filename, url, mime_type, subtitle }
  ) {
    this.editor.execute((state, dispatch) => {
      const transaction = state.tr,
        range = getRange();

      let nodeAttributes = {
        filename: filename,
        url: url,
        mime_type: mime_type,
      };

      if (subtitle) {
        nodeAttributes.subtitle = {
          filename: subtitle.filename,
          url: subtitle.url,
        };
      } else {
        // set subtitle if subtitleFile is not an object
        nodeAttributes.subtitle = subtitle;
      }

      const video = state.schema.node('video', nodeAttributes);
      dispatch(transaction.replaceWith(range.from, range.to, video));
    });
  }

  /**
   * @param {Function} getRange
   * @param {String} altText
   * @param {String} filename
   *
   * @private
   */
  async _updateImage(getRange, { filename, alttext }) {
    const info = await this._getFileInfo(filename);

    this.editor.execute((state, dispatch) => {
      const transaction = state.tr,
        range = getRange();

      let node = state.schema.node('image', {
        filename: filename,
        url: info.url,
        alttext: alttext,
      });

      dispatch(transaction.replaceWith(range.from, range.to, node));
      this.editor.view.focus();
    });
  }

  /**
   *
   * @param {Function}  getRange
   * @param {String}    filename
   * @param {Number}    size
   * @private
   */
  async _replaceAudioWithAttachment(getRange, { filename, size, transcript }) {
    const info = await this._getFileInfo(filename);

    this.editor.execute((state, dispatch) => {
      const transaction = state.tr,
        range = getRange();

      let attachmentAttrs = {
        filename: filename,
        url: info.url,
        size: size,
        option: {},
      };

      if (transcript) {
        attachmentAttrs.option.transcript = {
          url: transcript.url,
          filename: transcript.filename,
        };
      }

      let attachment = state.schema.node('attachment', attachmentAttrs);

      dispatch(
        transaction.replaceWith(
          range.from,
          range.to,
          state.schema.node('attachments', null, [attachment])
        )
      );
    });
  }

  async _updateAudioWithTranscript(
    getRange,
    { filename, url, mime_type, transcript }
  ) {
    this.editor.execute((state, dispatch) => {
      const transaction = state.tr,
        range = getRange();

      let nodeAttributes = {
        filename: filename,
        url: url,
        mime_type: mime_type,
      };

      if (transcript) {
        nodeAttributes.transcript = {
          filename: transcript.filename,
          url: transcript.url,
        };
      }

      let audio = state.schema.node('audio', nodeAttributes);
      dispatch(transaction.replaceWith(range.from, range.to, audio));
    });
  }

  _hasAttachmentNode() {
    return this.editor.hasNode('attachment');
  }

  _hasAttachmentsNode() {
    return this.editor.hasNode('attachments');
  }

  /**
   * @private
   * @param {string} filename
   */
  async _getFileInfo(filename) {
    return this.editor.fileStorage.getFileInfo(filename);
  }

  /**
   * @private
   * @param {string} filename
   */
  async _getDownloadUrl(filename) {
    const info = await this._getFileInfo(filename);
    return info.download_url;
  }

  /**
   * Returning the current item's id where the files are uploaded to.
   *
   * @return {Number | null}
   * @private
   */
  _getItemId() {
    return this.editor.fileStorage.getFileStorageItemId();
  }

  _getContextId() {
    return this.editor.identifier.contextId;
  }
}

export default opt => new MediaExtension(opt);
