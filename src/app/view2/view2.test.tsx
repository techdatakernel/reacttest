import { expect, test } from 'vitest';
import { render } from '@testing-library/react';
import View2 from './view2';
import 'element-internals-polyfill';

test('renders View2 component', () => {
  const wrapper = render(<View2 />);
  expect(wrapper).toBeTruthy();
});