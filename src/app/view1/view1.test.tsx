import { expect, test } from 'vitest';
import { render } from '@testing-library/react';
import View1 from './view1';
import 'element-internals-polyfill';

test('renders View1 component', () => {
  const wrapper = render(<View1 />);
  expect(wrapper).toBeTruthy();
});