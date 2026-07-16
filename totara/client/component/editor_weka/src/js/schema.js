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
 * @author Simon Chester <simon.chester@totaralearning.com>
 * @module editor_weka
 */

import { Schema } from 'ext_prosemirror/model';

const pdom = ['p', 0];

// essential nodes
const nodes = {
  doc: { content: 'block+' },

  paragraph: {
    group: 'block',
    content: 'inline*',
    parseDOM: [{ tag: 'p' }],
    toDOM() {
      return pdom;
    },
  },

  text: {
    group: 'inline',
  },
};

export function createSchema({ nodes: extraNodes, marks }) {
  return new Schema({
    nodes: Object.assign({}, nodes, extraNodes),
    marks: marks || {},
  });
}
