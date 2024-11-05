import { redirect } from 'react-router-dom';
import View1 from './view1/view1';
import View2 from './view2/view2';
import View3 from './view3/view3';

export const routes = [
  { index: true, loader: () => redirect('view1') },
  { path: 'view1', element: <View1 />, text: 'View1' },
  { path: 'view2', element: <View2 />, text: 'View2' },
  { path: 'view3', element: <View3 />, text: 'View3' }
];
