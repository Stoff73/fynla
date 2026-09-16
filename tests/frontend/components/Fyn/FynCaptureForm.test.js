import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import FynCaptureForm from '@/components/Fyn/FynCaptureForm.vue';

const schema = {
  name: 'property', submit_label: 'Save',
  kinds: [
    { key: 'main_residence', label: 'Home', fields: ['current_value', 'mortgage_outstanding_balance', 'ownership_type', 'ownership_percentage'] },
    { key: 'buy_to_let', label: 'Buy to let', fields: ['current_value', 'mortgage_outstanding_balance', 'monthly_rental_income', 'ownership_type', 'ownership_percentage'] },
  ],
  fields: {
    current_value: { type: 'money', label: 'Value', required: true, hint: 'The full value' },
    mortgage_outstanding_balance: { type: 'money_or_none', label: 'Mortgage outstanding', required: true, none_label: 'No mortgage' },
    monthly_rental_income: { type: 'money', label: 'Monthly rental income', required: true },
    ownership_type: { type: 'choice', label: 'Ownership', required: true, options: [{ value: 'individual', label: 'Individual' }, { value: 'joint', label: 'Joint' }, { value: 'tenants_in_common', label: 'Tenants in common' }] },
    ownership_percentage: { type: 'percent', label: 'Your share %', required: false, default: 50, required_when: { field: 'ownership_type', in: ['joint', 'tenants_in_common'] } },
  },
};

const box = (w, label) => w.findAll('button').find((b) => b.text() === label);

describe('FynCaptureForm', () => {
  it('shows the two kinds closed with Save disabled', () => {
    const w = mount(FynCaptureForm, { props: { schema } });
    expect(box(w, 'Home')).toBeTruthy();
    expect(box(w, 'Buy to let')).toBeTruthy();
    expect(w.find('input').exists()).toBe(false);
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeDefined();
  });

  it('opens Home to its fields with asterisks on the required ones', async () => {
    const w = mount(FynCaptureForm, { props: { schema } });
    await box(w, 'Home').trigger('click');
    const labels = w.findAll('label').map((l) => l.text());
    expect(labels).toContain('Value *');
    expect(labels).toContain('Mortgage outstanding *');
    expect(labels).toContain('Ownership *');
    expect(labels.some((l) => l.startsWith('Your share'))).toBe(false);
    expect(labels).not.toContain('Monthly rental income *');
  });

  it('reveals the share at 50 for Joint and enables Save once required fields are filled', async () => {
    const w = mount(FynCaptureForm, { props: { schema } });
    await box(w, 'Home').trigger('click');
    await w.find('input[name="main_residence.current_value"]').setValue('750000');
    await w.find('input[name="main_residence.mortgage_outstanding_balance"]').setValue('325000');
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeDefined();
    await w.find('input[type="radio"][value="joint"]').setValue(true);
    const share = w.find('input[name="main_residence.ownership_percentage"]');
    expect(share.exists()).toBe(true);
    expect(share.element.value).toBe('50');
    expect(w.findAll('label').map((l) => l.text())).toContain('Your share % *');
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeUndefined();
  });

  it('emits only the opened kinds, with No mortgage as null', async () => {
    const w = mount(FynCaptureForm, { props: { schema } });
    await box(w, 'Buy to let').trigger('click');
    await w.find('input[name="buy_to_let.current_value"]').setValue('450000');
    await w.find('input[name="buy_to_let.mortgage_outstanding_balance__none"]').setValue(true);
    await w.find('input[name="buy_to_let.monthly_rental_income"]').setValue('1000');
    await w.find('input[type="radio"][value="individual"]').setValue(true);
    await w.find('form').trigger('submit');
    expect(w.emitted('submit')[0][0]).toEqual({ name: 'property', answers: {
      buy_to_let: { current_value: 450000, mortgage_outstanding_balance: null, monthly_rental_income: 1000, ownership_type: 'individual' },
    } });
  });

  it('renders kind and field errors and locks with values', async () => {
    const w = mount(FynCaptureForm, { props: { schema, errors: { buy_to_let: { message: 'You have reached your plan\'s property limit.', fields: { current_value: 'Too large' } } } } });
    await box(w, 'Buy to let').trigger('click');
    expect(w.text()).toContain('property limit');
    expect(w.text()).toContain('Too large');

    const locked = mount(FynCaptureForm, { props: { schema, locked: true, values: { main_residence: { current_value: 750000, mortgage_outstanding_balance: 325000, ownership_type: 'joint', ownership_percentage: 50 } } } });
    expect(locked.find('button[type="submit"]').exists()).toBe(false);
    expect(locked.find('input[name="main_residence.current_value"]').element.value).toBe('750000');
    expect(locked.find('input[name="main_residence.current_value"]').attributes('disabled')).toBeDefined();
  });

  it('requires a text field to be non-empty, emits it trimmed, and never gates Save on an optional money field', async () => {
    const textSchema = {
      name: 'isa', submit_label: 'Save',
      kinds: [{ key: 'cash_isa', label: 'Cash ISA', fields: ['provider', 'current_value', 'paid_in_this_year', 'interest_rate'] }],
      fields: {
        provider: { type: 'text', label: 'Who is it with', required: true },
        current_value: { type: 'money', label: 'Current balance', required: true },
        paid_in_this_year: { type: 'money', label: 'Paid in this tax year', required: false, hint: 'Leave blank if none' },
        interest_rate: { type: 'percent', label: 'Interest rate %', required: false, min: 0, max: 20, step: 0.01 },
      },
    };
    const w = mount(FynCaptureForm, { props: { schema: textSchema } });
    await box(w, 'Cash ISA').trigger('click');
    expect(w.findAll('label').map((l) => l.text())).toContain('Who is it with *');
    const provider = w.find('input[name="cash_isa.provider"]');
    expect(provider.attributes('type')).toBe('text');
    await w.find('input[name="cash_isa.current_value"]').setValue('12000');
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeDefined();
    await provider.setValue('   ');
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeDefined();
    await provider.setValue('  Nationwide ');
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeUndefined();

    const rate = w.find('input[name="cash_isa.interest_rate"]');
    expect(rate.attributes('min')).toBe('0');
    expect(rate.attributes('max')).toBe('20');
    expect(rate.attributes('step')).toBe('0.01');

    await w.find('form').trigger('submit');
    expect(w.emitted('submit')[0][0]).toEqual({ name: 'isa', answers: { cash_isa: { provider: 'Nationwide', current_value: 12000 } } });
  });

  it('scrolls the opened kind into view and focuses its first field', async () => {
    const scrolled = [];
    const proto = window.HTMLElement.prototype;
    const original = proto.scrollIntoView;
    proto.scrollIntoView = function scrollIntoView() { scrolled.push(this.id); };
    try {
      const w = mount(FynCaptureForm, { props: { schema }, attachTo: document.body });
      await box(w, 'Buy to let').trigger('click');
      await w.vm.$nextTick();
      expect(scrolled).toEqual(['fyn-form-buy_to_let-current_value']);
      expect(document.activeElement && document.activeElement.id).toBe('fyn-form-buy_to_let-current_value');
      w.unmount();
    } finally {
      proto.scrollIntoView = original;
    }
  });


  it('asks the lead fields above the kind boxes, saves with only the lead filled, and posts them under _lead', async () => {
    const spouse = {
      name: 'spouse_household', submit_label: 'Save', tool: 'capture_spouse_household_data',
      lead_fields: ['spouse_annual_income'], kinds_prompt: 'Do they have any of the following? You can choose more than one.',
      kinds: [{ key: 'isa', label: 'ISAs', fields: ['spouse_isa_balance'] }],
      fields: {
        spouse_annual_income: { type: 'money', label: 'Their annual income', required: true },
        spouse_isa_balance: { type: 'money', label: 'ISA balance', required: true },
      },
    };
    const w = mount(FynCaptureForm, { props: { schema: spouse } });
    const html = w.html();
    expect(html.indexOf('Their annual income')).toBeLessThan(html.indexOf('Do they have any of the following'));
    expect(html.indexOf('Do they have any of the following')).toBeLessThan(html.indexOf('>ISAs<'));
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeDefined();
    await w.find('input[name="_lead.spouse_annual_income"]').setValue('45000');
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeUndefined();
    await box(w, 'ISAs').trigger('click');
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeDefined();
    await w.find('input[name="isa.spouse_isa_balance"]').setValue('12000');
    await w.find('form').trigger('submit');
    expect(w.emitted('submit')[0][0]).toEqual({ name: 'spouse_household', answers: { _lead: { spouse_annual_income: 45000 }, isa: { spouse_isa_balance: 12000 } } });
  });

  it('a schema that allows nothing chosen enables Save with no kind open', () => {
    const assets = { name: 'spouse_assets', submit_label: 'Save', allow_empty: true, lead_fields: [], kinds: [{ key: 'savings', label: 'Savings', fields: ['b'] }], fields: { b: { type: 'money', label: 'Savings balance', required: true } } };
    const w = mount(FynCaptureForm, { props: { schema: assets } });
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeUndefined();
  });

  it('binds the ownership-share bounds to a percent field without its own', async () => {
    const w = mount(FynCaptureForm, { props: { schema } });
    await box(w, 'Home').trigger('click');
    await w.find('input[type="radio"][value="joint"]').setValue(true);
    const share = w.find('input[name="main_residence.ownership_percentage"]');
    expect(share.attributes('min')).toBe('0.01');
    expect(share.attributes('max')).toBe('99.99');
    expect(share.attributes('step')).toBe('0.01');
  });

  it('renders a date field as a native date input, requires it, and posts it as YYYY-MM-DD with no kind boxes', async () => {
    const personal = {
      name: 'personal', submit_label: 'Save', tool: 'capture_personal_details', lead_fields: ['date_of_birth', 'marital_status'], kinds: [],
      fields: {
        date_of_birth: { type: 'date', label: 'Your date of birth', required: true },
        marital_status: { type: 'choice', label: 'Marital status', required: true, options: [{ value: 'single', label: 'Single' }, { value: 'married', label: 'Married' }] },
      },
    };
    const w = mount(FynCaptureForm, { props: { schema: personal } });
    expect(w.find('input[type="date"][name="_lead.date_of_birth"]').exists()).toBe(true);
    expect(w.findAll('button[type="button"]').length).toBe(0);
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeDefined();
    await w.find('input[name="_lead.date_of_birth"]').setValue('1985-01-12');
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeDefined();
    await w.find('input[type="radio"][value="married"]').setValue(true);
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeUndefined();
    await w.find('form').trigger('submit');
    expect(w.emitted('submit')[0][0]).toEqual({ name: 'personal', answers: { _lead: { date_of_birth: '1985-01-12', marital_status: 'married' } } });
  });

  it('renders an email field as a native email input and requires it', async () => {
    const spouse = {
      name: 'spouse_details', submit_label: 'Save', tool: 'capture_spouse_details', lead_fields: ['first_name', 'email'], kinds: [],
      fields: { first_name: { type: 'text', label: 'Their first name', required: true }, email: { type: 'email', label: 'Their email address', required: true } },
    };
    const w = mount(FynCaptureForm, { props: { schema: spouse } });
    expect(w.find('input[type="email"][name="_lead.email"]').exists()).toBe(true);
    await w.find('input[name="_lead.first_name"]').setValue('Jamie');
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeDefined();
    await w.find('input[name="_lead.email"]').setValue(' jamie@example.com ');
    expect(w.find('button[type="submit"]').attributes('disabled')).toBeUndefined();
    await w.find('form').trigger('submit');
    expect(w.emitted('submit')[0][0]).toEqual({ name: 'spouse_details', answers: { _lead: { first_name: 'Jamie', email: 'jamie@example.com' } } });
  });
});
