import React, {
  CSSProperties,
} from 'react';
import classnames from 'classnames';
import Select, { Props as ReactSelectProps, OptionProps } from 'react-select';

export type Props = ReactSelectProps & {
  dimension?: 'small';
  disabled?: boolean;
  isFullWidth?: boolean;
  iconStart?: JSX.Element;
  automationId?: string;
};

type LabelRenderer = {
  label: React.ReactNode;
  count?: React.ReactNode;
  tag?: React.ReactNode;
};

const LabelRenderer = (data: LabelRenderer) => (
  <div className="mailpoet-form-react-select-option">
    {data.tag && <span className="mailpoet-form-react-select-tag">{data.tag}</span>}
    <span>{data.label}</span>
    {data.count !== undefined && <span className="mailpoet-form-react-select-count">{data.count}</span>}
  </div>
);

type OptionData = {
  style: CSSProperties;
  label: React.ReactNode;
  count?: React.ReactNode;
  tag?: React.ReactNode;
}

const Option = (props: OptionProps<OptionData>) => {
  let style = {};
  if (props.data?.style) {
    style = props.data.style;
  }
  return (
    <div
      style={style}
      ref={props.innerRef}
      {...props.innerProps} // eslint-disable-line react/jsx-props-no-spreading
      className={
        classnames({
          'mailpoet-form-react-select__option': true,
          'mailpoet-form-react-select__option--is-disabled': props.isDisabled,
          'mailpoet-form-react-select__option--is-focused': props.isFocused,
          'mailpoet-form-react-select__option--is-selected': props.isSelected,
        })
      }
    >
      {LabelRenderer(props.data)}
    </div>
  );
};

// eslint-disable-next-line @typescript-eslint/no-explicit-any
const SingleValue = (props: any) => (
  <div
    {...props.innerProps} // eslint-disable-line react/jsx-props-no-spreading
    className={
      classnames({
        'mailpoet-form-react-select__single-value': true,
        'mailpoet-form-react-select__single-value--is-disabled': props.isDisabled,
      })
    }
  >
    {LabelRenderer(props.data)}
  </div>
);

type MultiValueLabel = {
  data: {
    style: CSSProperties;
    label: React.ReactNode;
    count?: React.ReactNode;
    tag?: React.ReactNode;
  };
  // eslint-disable-next-line @typescript-eslint/ban-types -- we need to match react-select
  innerProps: object;
};

const MultiValueLabel = (props: MultiValueLabel) => (
  <div
    {...props.innerProps} // eslint-disable-line react/jsx-props-no-spreading
    className="mailpoet-form-react-select__multi-value__label"
  >
    {LabelRenderer(props.data)}
  </div>
);

const ReactSelect = ({
  dimension,
  isFullWidth,
  iconStart,
  automationId,
  ...props
}: Props) => (
  <div
    className={
      classnames(
        'mailpoet-form-input',
        'mailpoet-form-select',
        {
          [`mailpoet-form-input-${dimension}`]: dimension,
          'mailpoet-disabled': props.disabled,
          'mailpoet-full-width': isFullWidth,
        }
      )
    }
    data-automation-id={automationId}
  >
    {iconStart}
    <Select
      className="mailpoet-form-react-select"
      classNamePrefix="mailpoet-form-react-select"
      components={{ Option, SingleValue, MultiValueLabel }}
      {...props} // eslint-disable-line react/jsx-props-no-spreading
    />
  </div>
);

export default ReactSelect;
