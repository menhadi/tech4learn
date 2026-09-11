import type { SelectHTMLAttributes } from "react";

const organisationTypes = [
  ["school", "School"],
  ["ngo", "NGO / Non-profit"],
  ["coaching", "Coaching centre / Academy"],
  ["csr", "CSR programme / Foundation"],
  ["government", "Government department / Programme"],
  ["other", "Other organisation"],
] as const;

export function OrganisationTypeSelect(
  props: SelectHTMLAttributes<HTMLSelectElement>,
) {
  return (
    <select {...props}>
      <option value="" disabled>
        Choose an organisation type
      </option>
      {organisationTypes.map(([value, label]) => (
        <option key={value} value={value}>
          {label}
        </option>
      ))}
    </select>
  );
}
