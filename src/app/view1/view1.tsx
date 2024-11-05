import styles from './view1.module.css';
import createClassTransformer from '../style-utils';

export default function View1() {
  const classes = createClassTransformer(styles);

  return (
    <>
      <div className={classes("row-layout view-1-container")}>
        <div className={classes("column-layout group")}>
          <h5 className={classes("h5")}>
            <span>View 1</span>
          </h5>
          <p className={classes("typography__body-1 text")}>
            <span>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</span>
          </p>
        </div>
      </div>
    </>
  );
}
