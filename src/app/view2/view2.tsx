import styles from './view2.module.css';
import createClassTransformer from '../style-utils';

export default function View2() {
  const classes = createClassTransformer(styles);

  return (
    <>
      <div className={classes("row-layout view-2-container")}></div>
    </>
  );
}
