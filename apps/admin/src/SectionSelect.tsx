import { useState } from "react";
import type { AcademicGroup } from "./AcademicStructure";
export function SectionSelect({
  groups,
  name = "group_id",
  value,
  onChange,
  initialValue = "",
  required = true,
  disabled = false,
}: {
  groups: AcademicGroup[];
  name?: string;
  value?: string;
  onChange?: (id: string) => void;
  initialValue?: string;
  required?: boolean;
  disabled?: boolean;
}) {
  const [selected, setSelected] = useState(initialValue);
  const initial = groups.find((g) => g.id === (value ?? initialValue));
  const [centre, setCentre] = useState(initial?.centre_id || ""),
    [year, setYear] = useState(
      initial ? initial.academic_year_id || "unassigned" : "",
    ),
    [klass, setClass] = useState(initial?.class_id || "");
  const change = (id: string) => {
    setSelected(id);
    onChange?.(id);
  };
  const inCentre = groups.filter((g) => !centre || g.centre_id === centre);
  const inYear = inCentre.filter(
    (g) => !year || (g.academic_year_id || "unassigned") === year,
  );
  const options = inYear.filter((g) => !klass || g.class_id === klass);
  return (
    <fieldset disabled={disabled} className="section-select">
      <div className="form-grid">
        <label>
          Centre
          <select
            value={centre}
            onChange={(e) => {
              setCentre(e.target.value);
              setYear("");
              setClass("");
              change("");
            }}
          >
            <option value="">All accessible centres</option>
            {[
              ...new Map(
                groups.map((g) => [g.centre_id, g.centre_name]),
              ).entries(),
            ].map(([id, label]) => (
              <option key={id} value={id}>
                {label}
              </option>
            ))}
          </select>
        </label>
        <label>
          Academic year
          <select
            value={year}
            onChange={(e) => {
              setYear(e.target.value);
              setClass("");
              change("");
            }}
          >
            <option value="">All years</option>
            {[
              ...new Map(
                inCentre.map((g) => [
                  g.academic_year_id || "unassigned",
                  g.year_name || "Unassigned groups",
                ]),
              ).entries(),
            ].map(([id, label]) => (
              <option key={id} value={id}>
                {label}
              </option>
            ))}
          </select>
        </label>
        <label>
          Class
          <select
            value={klass}
            onChange={(e) => {
              setClass(e.target.value);
              change("");
            }}
          >
            <option value="">All classes / groups</option>
            {[
              ...new Map(
                inYear
                  .filter((g) => g.class_id)
                  .map((g) => [g.class_id!, g.class_name!]),
              ).entries(),
            ].map(([id, label]) => (
              <option key={id} value={id}>
                {label}
              </option>
            ))}
          </select>
        </label>
        <label>
          Section / group
          <select
            name={name}
            value={value ?? selected}
            required={required}
            onChange={(e) => change(e.target.value)}
          >
            <option value="">
              {required ? "Choose a section / group" : "All sections / groups"}
            </option>
            {options.map((g) => (
              <option key={g.id} value={g.id}>
                {g.centre_name} / {g.display_name || g.name}
              </option>
            ))}
          </select>
        </label>
      </div>
    </fieldset>
  );
}
