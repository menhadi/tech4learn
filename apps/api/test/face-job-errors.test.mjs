import test from "node:test";
import assert from "node:assert/strict";
import {BadRequestException,ServiceUnavailableException,ForbiddenException} from "@nestjs/common";
import {faceJobError} from "../dist/face-jobs.service.js";
test("face jobs preserve actionable application errors and hide internal details",()=>{
 assert.equal(faceJobError(new BadRequestException("No checked references.")),"No checked references.");
 assert.equal(faceJobError(new ServiceUnavailableException("Face service rejected authentication.")),"Face service rejected authentication.");
 assert.match(faceJobError(new ForbiddenException()),/permissions/);
 assert.doesNotMatch(faceJobError(new Error("database password=synthetic-secret")),/synthetic-secret|password/);
});
