// Only a source-controlled SQL expression. The calling query must bind the group as g.
export const groupDisplaySql =
  "COALESCE((SELECT concat_ws(' / ',y.name,k.name,g.name) FROM learning_classes k JOIN academic_years y ON y.organisation_id=k.organisation_id AND y.id=k.academic_year_id WHERE k.organisation_id=g.organisation_id AND k.id=g.class_id),g.name)";
