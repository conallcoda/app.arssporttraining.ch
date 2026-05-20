import Checklist from '@editorjs/checklist';
import Delimiter from '@editorjs/delimiter';
import EditorJS from '@editorjs/editorjs';
import Header from '@editorjs/header';
import List from '@editorjs/list';
import Quote from '@editorjs/quote';
import Table from '@editorjs/table';
import Treeselect from 'treeselectjs';

import { registerDocumentWriter } from '../../../../coda-packages/form-kit/resources/js/alpine';
import '../../../../coda-packages/cms/resources/js/alpine';

import './athlete-mobile-shell';
import './alpine/schedule-grid';
import './alpine/calendar-cell-select';
import './alpine/calendar-slot-popover';
import './alpine/metric-cell-popover';
import './alpine/editable-cell';
import './alpine/org_chart_parse';

registerDocumentWriter({
    EditorJS,
    Checklist,
    Delimiter,
    Header,
    List,
    Quote,
    Table,
});

globalThis.Treeselect = Treeselect;
