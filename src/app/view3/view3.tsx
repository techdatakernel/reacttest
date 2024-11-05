import styles from './view3.module.css';
import createClassTransformer from '../style-utils';

export default function View3() {
  const classes = createClassTransformer(styles);

  return (
    <>
      <div className={classes("row-layout view-3-container")}></div>
    </>
  );
}
