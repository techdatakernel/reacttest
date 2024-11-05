import { expect, test } from 'vitest';
import { render } from '@testing-library/react';
import View3 from './view3';
import 'element-internals-polyfill';

test('renders View3 component', () => {
  const wrapper = render(<View3 />);
  expect(wrapper).toBeTruthy();
});